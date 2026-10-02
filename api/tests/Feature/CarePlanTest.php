<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Models\Tenant;
use App\Modules\ServiceUsers\Models\ServiceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarePlanTest extends TestCase
{
    use RefreshDatabase;

    protected function createSection(): array
    {
        return [
            'area' => 'mobility',
            'identified_need' => 'Needs assistance walking',
            'goal' => 'Walk independently with frame',
            'intervention' => 'Assist with frame twice daily',
            'frequency' => 'Twice daily',
        ];
    }

    public function test_creating_a_care_plan_versions_correctly_and_never_overwrites_history(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $serviceUser = ServiceUser::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'John',
            'last_name' => 'Smith',
        ]);

        $v1 = $this->actingAs($user)->postJson("/api/v1/service-users/{$serviceUser->id}/care-plans", [
            'effective_from' => '2026-01-01',
            'sections' => [$this->createSection()],
        ]);
        $v1->assertCreated()->assertJsonPath('data.version', 1)->assertJsonPath('data.status', 'active');
        $v1Id = $v1->json('data.id');
        $v1OriginalNeed = $v1->json('data.sections.0.identified_need');

        $secondSection = $this->createSection();
        $secondSection['identified_need'] = 'Now mostly independent, occasional support';

        $v2 = $this->actingAs($user)->postJson("/api/v1/service-users/{$serviceUser->id}/care-plans", [
            'effective_from' => '2026-06-01',
            'sections' => [$secondSection],
        ]);
        $v2->assertCreated()->assertJsonPath('data.version', 2)->assertJsonPath('data.status', 'active');

        // v1 must now be archived, but still readable and unchanged.
        $v1Fetched = $this->actingAs($user)->getJson("/api/v1/care-plans/{$v1Id}");
        $v1Fetched->assertOk()
            ->assertJsonPath('data.status', 'archived')
            ->assertJsonPath('data.sections.0.identified_need', $v1OriginalNeed);

        $history = $this->actingAs($user)->getJson("/api/v1/service-users/{$serviceUser->id}/care-plans");
        // Regression check: the list endpoint must eager-load sections, not
        // just the single-record show endpoint — the frontend's Care Plan
        // tab renders every version's sections straight from this list
        // response, and a missing 'sections' key (silently dropped by
        // Laravel's whenLoaded() when the relation isn't loaded) crashed the
        // whole page with no error boundary to catch it.
        $history->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.sections.0.identified_need', $secondSection['identified_need'])
            ->assertJsonPath('data.1.sections.0.identified_need', $v1OriginalNeed);
    }

    public function test_a_section_accepts_equipment_and_a_valid_risk_level(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'John', 'last_name' => 'Smith']);

        $section = $this->createSection();
        $section['risk'] = 'high';
        $section['equipment'] = 'Walking frame';

        $response = $this->actingAs($user)->postJson("/api/v1/service-users/{$serviceUser->id}/care-plans", [
            'effective_from' => '2026-01-01',
            'sections' => [$section],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.sections.0.risk', 'high')
            ->assertJsonPath('data.sections.0.equipment', 'Walking frame');
    }

    public function test_an_invalid_risk_level_is_rejected(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'John', 'last_name' => 'Smith']);

        $section = $this->createSection();
        $section['risk'] = 'extremely dangerous';

        $this->actingAs($user)->postJson("/api/v1/service-users/{$serviceUser->id}/care-plans", [
            'effective_from' => '2026-01-01',
            'sections' => [$section],
        ])->assertStatus(422);
    }

    public function test_the_list_endpoint_includes_responsible_staff_name(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $responsibleStaff = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Sarah Jones']);
        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'John', 'last_name' => 'Smith']);

        $section = $this->createSection();
        $section['responsible_staff_id'] = $responsibleStaff->id;

        $this->actingAs($user)->postJson("/api/v1/service-users/{$serviceUser->id}/care-plans", [
            'effective_from' => '2026-01-01',
            'sections' => [$section],
        ])->assertCreated();

        // Regression check: the list endpoint must eager-load
        // sections.responsibleStaff, not just sections — a missing eager
        // load is silently dropped by whenLoaded() rather than erroring,
        // so the care plan tab's "Responsible" field just went blank.
        $response = $this->actingAs($user)->getJson("/api/v1/service-users/{$serviceUser->id}/care-plans");
        $response->assertOk()->assertJsonPath('data.0.sections.0.responsible_staff_name', 'Sarah Jones');
    }

    public function test_risk_assessments_are_saved_scored_and_versioned_with_the_plan(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Sarah Jones']);
        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'John', 'last_name' => 'Smith']);

        $v1 = $this->actingAs($user)->postJson("/api/v1/service-users/{$serviceUser->id}/care-plans", [
            'effective_from' => '2026-01-01',
            'sections' => [$this->createSection()],
            'risk_assessments' => [
                [
                    'type' => 'general',
                    'area' => 'falls_prevention',
                    'hazard' => 'Falls when mobilising to the bathroom at night',
                    'persons_at_risk' => ['service_user', 'care_staff'],
                    'likelihood' => 4,
                    'severity' => 4,
                    'existing_controls' => 'Walking frame within reach',
                    'residual_likelihood' => 2,
                    'residual_severity' => 4,
                    'action_owner_id' => $owner->id,
                ],
                [
                    'type' => 'medication',
                    'area' => 'medication',
                    'hazard' => 'Missed or doubled doses of warfarin',
                    'persons_at_risk' => ['service_user'],
                    'likelihood' => 3,
                    'severity' => 5,
                    'medication_details' => [
                        'medication_name' => 'Warfarin',
                        'support_level' => 'administer',
                        'controlled_drug' => false,
                    ],
                ],
            ],
        ]);

        $v1->assertCreated()
            ->assertJsonCount(2, 'data.risk_assessments')
            ->assertJsonPath('data.risk_assessments.0.risk_score', 16)
            ->assertJsonPath('data.risk_assessments.0.residual_risk_score', 8)
            ->assertJsonPath('data.risk_assessments.0.persons_at_risk', ['service_user', 'care_staff'])
            ->assertJsonPath('data.risk_assessments.0.medication_details', null)
            ->assertJsonPath('data.risk_assessments.1.medication_details.medication_name', 'Warfarin');

        // A new version without risk assessments must leave v1's intact.
        $this->actingAs($user)->postJson("/api/v1/service-users/{$serviceUser->id}/care-plans", [
            'effective_from' => '2026-06-01',
            'sections' => [$this->createSection()],
        ])->assertCreated()->assertJsonCount(0, 'data.risk_assessments');

        $history = $this->actingAs($user)->getJson("/api/v1/service-users/{$serviceUser->id}/care-plans");
        $history->assertOk()
            ->assertJsonCount(0, 'data.0.risk_assessments')
            ->assertJsonCount(2, 'data.1.risk_assessments')
            ->assertJsonPath('data.1.risk_assessments.0.action_owner_name', 'Sarah Jones');
    }

    public function test_invalid_risk_assessment_input_is_rejected(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $otherTenant = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'Zimbabwe']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $outsider = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'John', 'last_name' => 'Smith']);

        $base = ['type' => 'general', 'hazard' => 'Falls'];
        $invalid = [
            'risk_assessments.0.likelihood' => [...$base, 'likelihood' => 6],
            'risk_assessments.0.persons_at_risk.0' => [...$base, 'persons_at_risk' => ['the cat']],
            'risk_assessments.0.type' => [...$base, 'type' => 'fire'],
            'risk_assessments.0.action_owner_id' => [...$base, 'action_owner_id' => $outsider->id],
            'risk_assessments.0.medication_details' => [...$base, 'type' => 'medication', 'medication_details' => ['unknown_key' => 'x']],
        ];

        foreach ($invalid as $errorKey => $riskAssessment) {
            $this->actingAs($user)->postJson("/api/v1/service-users/{$serviceUser->id}/care-plans", [
                'effective_from' => '2026-01-01',
                'sections' => [$this->createSection()],
                'risk_assessments' => [$riskAssessment],
            ])->assertStatus(422)->assertJsonValidationErrors($errorKey);
        }
    }

    public function test_only_one_active_care_plan_per_service_user(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $serviceUser = ServiceUser::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'John',
            'last_name' => 'Smith',
        ]);

        $this->actingAs($user)->postJson("/api/v1/service-users/{$serviceUser->id}/care-plans", [
            'effective_from' => '2026-01-01',
            'sections' => [$this->createSection()],
        ])->assertCreated();

        $this->actingAs($user)->postJson("/api/v1/service-users/{$serviceUser->id}/care-plans", [
            'effective_from' => '2026-06-01',
            'sections' => [$this->createSection()],
        ])->assertCreated();

        $activeCount = $serviceUser->carePlans()->where('status', 'active')->count();
        $this->assertSame(1, $activeCount);
    }
}
