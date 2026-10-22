<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\MedicalHistory\Models\MedicalHistoryEntry;
use App\Modules\Organization\Models\Tenant;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\Visits\Models\Visit;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MedicalHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $carer;

    protected ServiceUser $serviceUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $this->carer = $this->userWithRole('Carer / Support Worker');
        $this->serviceUser = ServiceUser::create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Ruth',
            'last_name' => 'Chikafu',
        ]);
    }

    protected function userWithRole(string $role): User
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
        $user->assignRole(Role::where(['name' => $role, 'tenant_id' => $this->tenant->id])->firstOrFail());

        return $user;
    }

    protected function record(array $entry, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->carer)
            ->postJson("/api/v1/service-users/{$this->serviceUser->id}/medical-history", $entry);
    }

    public function test_staff_can_record_a_detailed_history_that_fills_in_the_summary_lists(): void
    {
        $this->record([
            'category' => 'allergy',
            'name' => 'Penicillin',
            'allergy_type' => 'medication',
            'severity' => 'life_threatening',
            'reaction' => 'Anaphylaxis',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.severity', 'life_threatening')
            ->assertJsonPath('data.recorded_by_name', $this->carer->name);

        $this->record(['category' => 'condition', 'name' => 'Type 2 diabetes', 'started_on' => '2009-04-20', 'provider' => 'GP'])->assertCreated();
        $this->record(['category' => 'mental_health', 'name' => 'Depression', 'status' => 'in_remission'])->assertCreated();
        $this->record(['category' => 'disability', 'name' => 'Hearing loss'])->assertCreated();
        $this->record(['category' => 'admission', 'name' => 'Fractured wrist', 'started_on' => '2024-02-03', 'ended_on' => '2024-02-08'])->assertCreated();

        $this->actingAs($this->carer)
            ->getJson("/api/v1/service-users/{$this->serviceUser->id}/medical-history")
            ->assertOk()
            ->assertJsonCount(5, 'data')
            // Most recent first, undated entries last.
            ->assertJsonPath('data.0.name', 'Fractured wrist');

        $this->actingAs($this->carer)
            ->getJson("/api/v1/service-users/{$this->serviceUser->id}")
            ->assertJsonPath('data.allergies', ['Penicillin'])
            ->assertJsonPath('data.diagnoses', ['Type 2 diabetes', 'Depression'])
            ->assertJsonPath('data.medical_conditions', ['Type 2 diabetes', 'Depression'])
            ->assertJsonPath('data.disabilities', ['Hearing loss']);
    }

    public function test_a_resolved_entry_stays_in_the_history_but_leaves_the_summary(): void
    {
        $id = $this->record(['category' => 'condition', 'name' => 'Pneumonia'])->json('data.id');

        $this->actingAs($this->carer)
            ->patchJson("/api/v1/medical-history/{$id}", ['status' => 'resolved', 'ended_on' => '2025-03-01'])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.ended_on', '2025-03-01')
            ->assertJsonPath('data.name', 'Pneumonia');

        $this->assertSame([], $this->serviceUser->fresh()->medical_conditions);
        $this->assertDatabaseHas('medical_history_entries', ['id' => $id, 'status' => 'resolved']);
    }

    public function test_fields_a_category_does_not_record_are_cleared(): void
    {
        $this->record([
            'category' => 'family_history',
            'name' => 'Lung cancer',
            'relationship' => 'Father',
            'severity' => 'severe',
            'started_on' => '2001-01-01',
        ])->assertCreated()
            ->assertJsonPath('data.relationship', 'Father')
            ->assertJsonPath('data.severity', null)
            ->assertJsonPath('data.started_on', null)
            ->assertJsonPath('data.status', null);

        $allergyId = $this->record(['category' => 'allergy', 'name' => 'Latex', 'severity' => 'mild', 'reaction' => 'Rash'])->json('data.id');

        // Re-filed as a condition, the allergy-only details go.
        $this->actingAs($this->carer)
            ->patchJson("/api/v1/medical-history/{$allergyId}", ['category' => 'condition', 'name' => 'Contact dermatitis'])
            ->assertOk()
            ->assertJsonPath('data.severity', null)
            ->assertJsonPath('data.reaction', null)
            ->assertJsonPath('data.status', 'active');

        $this->assertSame([], $this->serviceUser->fresh()->allergies);
    }

    public function test_entries_are_validated(): void
    {
        $this->record(['category' => 'surgery', 'name' => 'Appendix'])->assertJsonValidationErrors('category');
        $this->record(['category' => 'condition'])->assertJsonValidationErrors('name');
        $this->record(['category' => 'allergy', 'name' => 'Nuts', 'severity' => 'awful'])->assertJsonValidationErrors('severity');
        $this->record(['category' => 'admission', 'name' => 'Fall', 'started_on' => '2025-05-10', 'ended_on' => '2025-05-01'])
            ->assertJsonValidationErrors('ended_on');
    }

    public function test_no_known_allergies_is_confirmed_separately_and_cleared_by_a_recorded_allergy(): void
    {
        $this->actingAs($this->carer)
            ->patchJson("/api/v1/service-users/{$this->serviceUser->id}", ['no_known_allergies' => true])
            ->assertOk()
            ->assertJsonPath('data.no_known_allergies', true);

        $this->record(['category' => 'allergy', 'name' => 'Aspirin'])->assertCreated();
        $this->assertFalse($this->serviceUser->fresh()->no_known_allergies);

        // …and can't be confirmed again while that allergy is on record.
        $this->actingAs($this->carer)
            ->patchJson("/api/v1/service-users/{$this->serviceUser->id}", ['no_known_allergies' => true])
            ->assertJsonValidationErrors('no_known_allergies');
    }

    public function test_the_summary_lists_cannot_be_set_directly(): void
    {
        $this->actingAs($this->carer)
            ->patchJson("/api/v1/service-users/{$this->serviceUser->id}", ['allergies' => ['Made up'], 'smoking_status' => 'former'])
            ->assertOk()
            ->assertJsonPath('data.allergies', [])
            ->assertJsonPath('data.smoking_status', 'former');
    }

    public function test_only_whoever_recorded_an_entry_or_a_clinical_lead_can_delete_it(): void
    {
        $firstId = $this->record(['category' => 'condition', 'name' => 'Asthma'])->json('data.id');
        $secondId = $this->record(['category' => 'condition', 'name' => 'Gout'])->json('data.id');

        $otherCarer = $this->userWithRole('Carer / Support Worker');
        $this->actingAs($otherCarer)->deleteJson("/api/v1/medical-history/{$firstId}")->assertForbidden();

        $this->actingAs($this->carer)->deleteJson("/api/v1/medical-history/{$firstId}")->assertNoContent();
        $this->actingAs($this->userWithRole('Nurse'))->deleteJson("/api/v1/medical-history/{$secondId}")->assertNoContent();

        $this->assertDatabaseCount('medical_history_entries', 0);
        $this->assertSame([], $this->serviceUser->fresh()->medical_conditions);
    }

    public function test_a_clinical_lead_signs_the_history_off_once_allergy_status_is_known(): void
    {
        $url = "/api/v1/service-users/{$this->serviceUser->id}/medical-history/review";
        $manager = $this->userWithRole('Care Manager');

        $this->actingAs($this->carer)->postJson($url)->assertForbidden();
        $this->actingAs($manager)->postJson($url)->assertJsonValidationErrors('allergies');

        $this->serviceUser->update(['no_known_allergies' => true]);

        $this->actingAs($manager)->postJson($url)
            ->assertOk()
            ->assertJsonPath('data.medical_history_reviewed_by', $manager->id)
            ->assertJsonPath('data.medical_history_reviewed_by_name', $manager->name);

        $this->assertNotNull($this->serviceUser->fresh()->medical_history_reviewed_at);
    }

    public function test_another_tenant_cannot_read_or_change_the_history(): void
    {
        // Set up before any request — after one, the session's tenant is A
        // and row-level security won't let tenant B's user be inserted.
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

        $entryId = $this->record(['category' => 'condition', 'name' => 'Epilepsy'])->json('data.id');

        // Refused by the controller (403) or, once tenant B's context is set
        // on the connection, never found at all (404) — either way, denied.
        $responses = [
            $this->actingAs($userB)->getJson("/api/v1/service-users/{$this->serviceUser->id}/medical-history"),
            $this->actingAs($userB)->postJson("/api/v1/service-users/{$this->serviceUser->id}/medical-history", ['category' => 'condition', 'name' => 'X']),
            $this->actingAs($userB)->patchJson("/api/v1/medical-history/{$entryId}", ['name' => 'Changed']),
            $this->actingAs($userB)->deleteJson("/api/v1/medical-history/{$entryId}"),
        ];
        foreach ($responses as $response) {
            $this->assertContains($response->status(), [403, 404]);
        }

        // Clear tenant B's context so the check below can see tenant A's rows.
        app(TenantContext::class)->set(null);
        $this->assertSame(['Epilepsy'], MedicalHistoryEntry::pluck('name')->all());
    }

    public function test_a_carers_visits_carry_the_clients_allergies_and_resuscitation_status(): void
    {
        $this->record(['category' => 'allergy', 'name' => 'Penicillin', 'severity' => 'life_threatening', 'reaction' => 'Anaphylaxis'])->assertCreated();
        $this->record(['category' => 'allergy', 'name' => 'Plasters', 'status' => 'resolved'])->assertCreated();
        $this->serviceUser->update(['resuscitation_status' => 'dnacpr', 'resuscitation_notes' => 'Form in the red folder']);

        $visit = Visit::create([
            'tenant_id' => $this->tenant->id,
            'service_user_id' => $this->serviceUser->id,
            'carer_id' => $this->carer->id,
            'visit_date' => '2026-09-10',
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);

        $expected = [
            'allergies' => [['name' => 'Penicillin', 'severity' => 'life_threatening', 'reaction' => 'Anaphylaxis']],
            'no_known_allergies' => false,
            'resuscitation_status' => 'dnacpr',
            'resuscitation_notes' => 'Form in the red folder',
        ];

        $this->actingAs($this->carer)->getJson("/api/v1/visits/{$visit->id}")
            ->assertOk()
            ->assertJsonPath('data.client_alerts', $expected);

        $this->actingAs($this->carer)->getJson("/api/v1/visits?carer_id={$this->carer->id}&date=2026-09-10")
            ->assertOk()
            ->assertJsonPath('data.0.client_alerts', $expected);
    }

    public function test_data_checks_flag_missing_and_unreviewed_histories(): void
    {
        $owner = $this->userWithRole('Organization Owner');
        $reviewed = ServiceUser::create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Peter',
            'last_name' => 'Sibanda',
            'no_known_allergies' => true,
            'medical_history_reviewed_at' => now()->subMonth(),
        ]);
        $this->actingAs($this->carer)
            ->postJson("/api/v1/service-users/{$reviewed->id}/medical-history", ['category' => 'condition', 'name' => 'COPD'])
            ->assertCreated();

        $checks = collect($this->actingAs($owner)->getJson('/api/v1/data-maintenance/checks')->assertOk()->json('data'))->keyBy('key');

        // Ruth has nothing recorded at all; Peter is complete and reviewed.
        foreach (['clients_allergies_unknown', 'clients_without_medical_history', 'medical_history_review_due'] as $key) {
            $this->assertSame(1, $checks[$key]['count'], $key);
            $this->assertSame('Ruth Chikafu', $checks[$key]['sample'][0]['label'], $key);
        }
    }
}
