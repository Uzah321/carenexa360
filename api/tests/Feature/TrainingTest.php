<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Models\Tenant;
use App\Modules\Training\Models\TrainingCourse;
use App\Modules\Training\Models\TrainingRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TrainingTest extends TestCase
{
    use RefreshDatabase;

    protected function makeComplianceAdmin(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $role = Role::where(['name' => 'Compliance Officer', 'tenant_id' => $tenant->id])->firstOrFail();
        $admin->assignRole($role);

        return compact('tenant', 'admin');
    }

    public function test_a_staff_member_cannot_self_report_a_training_record(): void
    {
        ['tenant' => $tenant] = $this->makeComplianceAdmin();
        $carer = User::factory()->create(['tenant_id' => $tenant->id]);
        $course = TrainingCourse::create(['tenant_id' => $tenant->id, 'name' => 'Manual Handling']);

        $this->actingAs($carer)->postJson('/api/v1/training-records', [
            'user_id' => $carer->id,
            'training_course_id' => $course->id,
            'completed_date' => '2026-08-01',
        ])->assertForbidden();
    }

    public function test_compliance_officer_can_log_a_record_with_auto_computed_expiry(): void
    {
        ['tenant' => $tenant, 'admin' => $admin] = $this->makeComplianceAdmin();
        $carer = User::factory()->create(['tenant_id' => $tenant->id]);
        $course = TrainingCourse::create([
            'tenant_id' => $tenant->id,
            'name' => 'Manual Handling',
            'validity_period_months' => 24,
        ]);

        $response = $this->actingAs($admin)->postJson('/api/v1/training-records', [
            'user_id' => $carer->id,
            'training_course_id' => $course->id,
            'completed_date' => '2026-01-01',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.expiry_date', '2028-01-01')
            ->assertJsonPath('data.status', 'valid');
    }

    public function test_training_record_status_reflects_expiry(): void
    {
        ['tenant' => $tenant, 'admin' => $admin] = $this->makeComplianceAdmin();
        $course = TrainingCourse::create(['tenant_id' => $tenant->id, 'name' => 'Fire Safety']);

        $expired = TrainingRecord::create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'training_course_id' => $course->id,
            'completed_date' => '2020-01-01',
            'expiry_date' => now()->subDay()->toDateString(),
        ]);

        $expiringSoon = TrainingRecord::create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'training_course_id' => $course->id,
            'completed_date' => '2024-01-01',
            'expiry_date' => now()->addDays(10)->toDateString(),
        ]);

        $noExpiry = TrainingRecord::create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'training_course_id' => $course->id,
            'completed_date' => '2024-01-01',
        ]);

        $this->assertEquals('expired', $expired->status);
        $this->assertEquals('expiring_soon', $expiringSoon->status);
        $this->assertEquals('no_expiry', $noExpiry->status);
    }

    public function test_non_compliance_user_only_sees_their_own_training_records(): void
    {
        ['tenant' => $tenant, 'admin' => $admin] = $this->makeComplianceAdmin();
        $carer = User::factory()->create(['tenant_id' => $tenant->id]);
        $course = TrainingCourse::create(['tenant_id' => $tenant->id, 'name' => 'Fire Safety']);

        TrainingRecord::create([
            'tenant_id' => $tenant->id,
            'user_id' => $carer->id,
            'training_course_id' => $course->id,
            'completed_date' => '2026-01-01',
        ]);
        TrainingRecord::create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'training_course_id' => $course->id,
            'completed_date' => '2026-01-01',
        ]);

        $response = $this->actingAs($carer)->getJson('/api/v1/training-records');

        $response->assertOk()->assertJsonCount(1, 'data');
    }
}
