<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Models\Tenant;
use App\Modules\Quality\Models\Complaint;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\Staff\Models\StaffProfile;
use App\Notifications\AssignmentNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class QualityTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'UK']);
        $this->manager = $this->userWithRole('Care Manager');
    }

    protected function userWithRole(string $role, ?Tenant $tenant = null): User
    {
        $tenant ??= $this->tenant;
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->assignRole(Role::where(['name' => $role, 'tenant_id' => $tenant->id])->firstOrFail());

        return $user;
    }

    protected function staff(string $name): User
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => $name]);
        StaffProfile::create(['tenant_id' => $this->tenant->id, 'user_id' => $user->id]);

        return $user;
    }

    public function test_carers_cannot_see_or_log_complaints_or_spot_checks(): void
    {
        $carer = $this->userWithRole('Carer / Support Worker');

        $this->actingAs($carer)->getJson('/api/v1/complaints')->assertForbidden();
        $this->actingAs($carer)->postJson('/api/v1/complaints', [])->assertForbidden();
        $this->actingAs($carer)->getJson('/api/v1/spot-checks')->assertForbidden();
    }

    public function test_logging_a_complaint_sets_a_response_date_and_emails_the_investigator(): void
    {
        $investigator = $this->staff('Grace Mutasa');
        $ruth = ServiceUser::create(['tenant_id' => $this->tenant->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu']);

        $response = $this->actingAs($this->manager)->postJson('/api/v1/complaints', [
            'service_user_id' => $ruth->id,
            'received_date' => '2026-06-01',
            'complainant_name' => 'Tendai Chikafu',
            'complainant_relationship' => 'Son',
            'category' => 'timekeeping',
            'severity' => 'medium',
            'description' => 'Carers arriving over an hour late most mornings.',
            'assigned_to' => $investigator->id,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('data.response_due_date', '2026-06-29')
            ->assertJsonPath('data.service_user_name', 'Ruth Chikafu');

        Notification::assertSentTo($investigator, AssignmentNotification::class, fn ($n) => $n->kind === 'complaint');

        $id = $response->json('data.id');
        $this->actingAs($this->manager)->patchJson("/api/v1/complaints/{$id}", ['status' => 'resolved', 'outcome' => 'upheld'])
            ->assertOk()
            ->assertJsonPath('data.resolved_date', now()->toDateString())
            ->assertJsonPath('data.is_overdue', false);

        $this->actingAs($this->manager)->postJson('/api/v1/complaints', ['received_date' => '2026-06-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['complainant_name', 'category', 'description']);
    }

    public function test_open_complaints_past_their_due_date_are_overdue(): void
    {
        $complaint = Complaint::create([
            'tenant_id' => $this->tenant->id, 'received_date' => now()->subDays(40), 'complainant_name' => 'A',
            'category' => 'other', 'description' => 'x', 'status' => 'investigating', 'response_due_date' => now()->subDays(12),
        ]);

        $this->assertTrue($complaint->isOverdue());
        $complaint->update(['status' => 'closed']);
        $this->assertFalse($complaint->fresh()->isOverdue());
    }

    public function test_spot_check_outcome_follows_the_failed_areas(): void
    {
        $amy = $this->staff('Amy Moyo');
        $check = fn (array $results) => $this->actingAs($this->manager)->postJson('/api/v1/spot-checks', [
            'staff_user_id' => $amy->id, 'check_date' => '2026-06-10', 'results' => $results,
        ]);

        $check(['punctuality' => 'pass', 'infection_control' => 'pass', 'medication' => 'na'])->assertCreated()
            ->assertJsonPath('data.outcome', 'pass')
            ->assertJsonPath('data.checked_by', $this->manager->id);
        $check(['punctuality' => 'fail', 'infection_control' => 'pass'])->assertCreated()->assertJsonPath('data.outcome', 'needs_improvement');
        $id = $check(['punctuality' => 'fail', 'record_keeping' => 'fail'])->assertCreated()->assertJsonPath('data.outcome', 'fail')->json('data.id');

        // Re-marking the results recalculates the outcome.
        $this->actingAs($this->manager)->patchJson("/api/v1/spot-checks/{$id}", ['results' => ['punctuality' => 'pass', 'record_keeping' => 'pass']])
            ->assertOk()->assertJsonPath('data.outcome', 'pass');

        $check(['juggling' => 'pass'])->assertUnprocessable()->assertJsonValidationErrors('results');
        $check(['punctuality' => 'great'])->assertUnprocessable()->assertJsonValidationErrors('results.punctuality');
    }

    public function test_spot_checks_only_cover_this_tenants_staff(): void
    {
        $other = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);
        $outsider = User::factory()->create(['tenant_id' => $other->id]);
        StaffProfile::create(['tenant_id' => $other->id, 'user_id' => $outsider->id]);

        $this->actingAs($this->manager)->postJson('/api/v1/spot-checks', [
            'staff_user_id' => $outsider->id, 'check_date' => '2026-06-10', 'results' => ['punctuality' => 'pass'],
        ])->assertUnprocessable()->assertJsonValidationErrors('staff_user_id');
    }

    public function test_complaint_spot_check_and_quality_indicator_reports(): void
    {
        $amy = $this->staff('Amy Moyo');
        Complaint::create([
            'tenant_id' => $this->tenant->id, 'received_date' => '2026-06-02', 'complainant_name' => 'Tendai', 'category' => 'communication',
            'description' => 'x', 'status' => 'resolved', 'response_due_date' => '2026-06-30', 'resolved_date' => '2026-06-12', 'outcome' => 'partially_upheld',
        ]);
        $this->actingAs($this->manager)->postJson('/api/v1/spot-checks', [
            'staff_user_id' => $amy->id, 'check_date' => '2026-06-10', 'results' => ['punctuality' => 'fail', 'communication' => 'pass'],
            'actions_required' => 'Plan routes to arrive on time',
        ])->assertCreated();

        $get = fn (string $key) => $this->actingAs($this->manager)->getJson("/api/v1/reports/generate?key={$key}&from=2026-06-01&to=2026-06-30")->assertOk();

        $get('complaints')->assertJsonPath('rows.0.days', 10)->assertJsonPath('rows.0.response', 'On time')->assertJsonPath('rows.0.outcome', 'Partially upheld');
        $get('spot_checks')->assertJsonPath('rows.0.outcome', 'Needs improvement')->assertJsonPath('rows.0.failed', 'Punctuality');

        $kpis = collect($get('service_quality_indicators')->json('rows'))->pluck('value', 'indicator');
        $this->assertSame(1, $kpis['Complaints received']);
        $this->assertSame('100%', $kpis['Complaints resolved on time']);
        $this->assertSame('0%', $kpis['Spot checks passed']);
    }
}
