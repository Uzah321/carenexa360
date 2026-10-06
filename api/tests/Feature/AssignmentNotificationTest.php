<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Organization\Models\Tenant;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\Staff\Models\StaffProfile;
use App\Notifications\AssignmentNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AssignmentNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
    }

    protected function userWithRole(string $role): User
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
        $user->assignRole(Role::where(['name' => $role, 'tenant_id' => $this->tenant->id])->firstOrFail());

        return $user;
    }

    protected function staff(string $name = 'Sarah Jones'): User
    {
        return User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => $name]);
    }

    protected function assertEmailed(User $user, string $kind, ?string $contains = null): void
    {
        Notification::assertSentTo($user, AssignmentNotification::class, function (AssignmentNotification $n) use ($kind, $contains, $user) {
            if ($n->kind !== $kind) {
                return false;
            }
            $mail = $n->toMail($user);
            $text = implode("\n", [$mail->subject, ...$mail->introLines]);

            return $contains === null || str_contains($text, $contains);
        });
    }

    public function test_a_new_shift_emails_the_staff_member(): void
    {
        $admin = $this->userWithRole('Organization Admin');
        $staff = $this->staff();

        $this->actingAs($admin)->postJson('/api/v1/shifts', [
            'user_id' => $staff->id,
            'shift_date' => '2026-09-10',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'shift_type' => 'night',
        ])->assertCreated();

        $this->assertEmailed($staff, 'shift', 'night shift');
    }

    public function test_incident_assignment_emails_on_create_and_on_reassignment_only(): void
    {
        $admin = $this->userWithRole('Organization Admin');
        $first = $this->staff('First Investigator');
        $second = $this->staff('Second Investigator');

        $id = $this->actingAs($admin)->postJson('/api/v1/incidents', [
            'type' => 'fall',
            'severity' => 'high',
            'description' => 'Slipped in the bathroom.',
            'assigned_to' => $first->id,
        ])->assertCreated()->json('data.id');
        $this->assertEmailed($first, 'incident', 'Severity: High');

        // An edit that keeps the same assignee emails no one.
        $this->actingAs($admin)->patchJson("/api/v1/incidents/{$id}", ['status' => 'investigating'])->assertOk();
        Notification::assertSentToTimes($first, AssignmentNotification::class, 1);

        $this->actingAs($admin)->patchJson("/api/v1/incidents/{$id}", ['assigned_to' => $second->id])->assertOk();
        $this->assertEmailed($second, 'incident');
        Notification::assertSentToTimes($first, AssignmentNotification::class, 1);
    }

    public function test_unassigned_incidents_email_no_one(): void
    {
        $admin = $this->userWithRole('Organization Admin');

        $this->actingAs($admin)->postJson('/api/v1/incidents', [
            'type' => 'fall',
            'severity' => 'low',
            'description' => 'Minor trip.',
        ])->assertCreated();

        Notification::assertNothingSent();
    }

    public function test_compliance_requirement_emails_the_responsible_person(): void
    {
        $officer = $this->userWithRole('Compliance Officer');
        $owner = $this->staff();
        $next = $this->staff('Next Owner');

        $id = $this->actingAs($officer)->postJson('/api/v1/compliance-requirements', [
            'name' => 'Public Liability Insurance',
            'responsible_user_id' => $owner->id,
            'renewal_date' => '2027-01-31',
        ])->assertCreated()->json('data.id');
        $this->assertEmailed($owner, 'compliance_requirement', 'Renewal due: 2027-01-31');

        $this->actingAs($officer)->patchJson("/api/v1/compliance-requirements/{$id}", ['responsible_user_id' => $next->id])->assertOk();
        $this->assertEmailed($next, 'compliance_requirement', 'Public Liability Insurance');
    }

    public function test_care_manager_is_emailed_when_set_or_changed(): void
    {
        $admin = $this->userWithRole('Organization Admin');
        $manager = $this->staff();
        $next = $this->staff('Next Manager');

        $id = $this->actingAs($admin)->postJson('/api/v1/service-users', [
            'first_name' => 'Ruth',
            'last_name' => 'Chikafu',
            'care_manager_id' => $manager->id,
        ])->assertCreated()->json('data.id');
        $this->assertEmailed($manager, 'care_manager', 'Ruth Chikafu');

        $this->actingAs($admin)->patchJson("/api/v1/service-users/{$id}", ['phone' => '0771 000 000'])->assertOk();
        Notification::assertSentToTimes($manager, AssignmentNotification::class, 1);

        $this->actingAs($admin)->patchJson("/api/v1/service-users/{$id}", ['care_manager_id' => $next->id])->assertOk();
        $this->assertEmailed($next, 'care_manager');
    }

    public function test_care_plan_emails_only_newly_given_responsibilities_one_email_per_person(): void
    {
        $admin = $this->userWithRole('Organization Admin');
        $sarah = $this->staff('Sarah Jones');
        $tom = $this->staff('Tom Banda');
        $serviceUser = ServiceUser::create(['tenant_id' => $this->tenant->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu']);

        $section = fn (string $area, ?int $staffId) => [
            'area' => $area, 'identified_need' => 'Need', 'goal' => 'Goal', 'intervention' => 'Do it', 'responsible_staff_id' => $staffId,
        ];
        $risk = fn (string $hazard, ?int $ownerId) => ['type' => 'general', 'hazard' => $hazard, 'action_owner_id' => $ownerId];

        $this->actingAs($admin)->postJson("/api/v1/service-users/{$serviceUser->id}/care-plans", [
            'effective_from' => '2026-01-01',
            'sections' => [$section('mobility', $sarah->id), $section('nutrition', $sarah->id)],
            'risk_assessments' => [$risk('Falls on stairs', $sarah->id)],
        ])->assertCreated();

        Notification::assertSentToTimes($sarah, AssignmentNotification::class, 1);
        $this->assertEmailed($sarah, 'care_plan', 'Action owner for risk: Falls on stairs');

        // v2: Sarah keeps everything; Tom newly takes nutrition. Only Tom hears about it.
        $this->actingAs($admin)->postJson("/api/v1/service-users/{$serviceUser->id}/care-plans", [
            'effective_from' => '2026-02-01',
            'sections' => [$section('mobility', $sarah->id), $section('nutrition', $tom->id)],
            'risk_assessments' => [$risk('Falls on stairs', $sarah->id)],
        ])->assertCreated();

        Notification::assertSentToTimes($sarah, AssignmentNotification::class, 1);
        $this->assertEmailed($tom, 'care_plan', 'Responsible for care area: Nutrition');
    }

    public function test_new_accounts_and_role_changes_email_the_user(): void
    {
        $owner = $this->userWithRole('Organization Owner');

        $this->actingAs($owner)->postJson('/api/v1/user-roles', [
            'name' => 'New Carer',
            'email' => 'new.carer@example.com',
            'password' => 'secret-password',
            'role' => 'Carer / Support Worker',
        ])->assertCreated();
        $newUser = User::where('email', 'new.carer@example.com')->firstOrFail();
        $this->assertEmailed($newUser, 'role', 'Carer / Support Worker');

        $carer = $this->staff('Existing Carer');
        StaffProfile::create(['tenant_id' => $this->tenant->id, 'user_id' => $carer->id]);
        $carer->assignRole(Role::where(['name' => 'Carer / Support Worker', 'tenant_id' => $this->tenant->id])->firstOrFail());

        // Re-saving the same role isn't a new assignment.
        $this->actingAs($owner)->patchJson("/api/v1/user-roles/{$carer->id}", ['role' => 'Carer / Support Worker'])->assertOk();
        Notification::assertNotSentTo($carer, AssignmentNotification::class);

        $this->actingAs($owner)->patchJson("/api/v1/user-roles/{$carer->id}", ['role' => 'Senior Carer'])->assertOk();
        $this->assertEmailed($carer, 'role', 'Senior Carer');
    }

    public function test_a_mail_failure_does_not_break_the_assignment(): void
    {
        Notification::swap(new class
        {
            public function send(): void
            {
                throw new \RuntimeException('SMTP down');
            }

            public function __call($method, $args) {}
        });
        $admin = $this->userWithRole('Organization Admin');
        $staff = $this->staff();

        $this->actingAs($admin)->postJson('/api/v1/incidents', [
            'type' => 'fall',
            'severity' => 'low',
            'description' => 'Trip.',
            'assigned_to' => $staff->id,
        ])->assertCreated();

        $this->assertSame(1, Incident::count());
    }

    protected function carer(string $name): User
    {
        $user = $this->staff($name);
        StaffProfile::create(['tenant_id' => $this->tenant->id, 'user_id' => $user->id]);

        return $user;
    }

    public function test_setting_a_clients_carers_emails_only_newly_added_carers(): void
    {
        $admin = $this->userWithRole('Organization Admin');
        $amy = $this->carer('Amy Moyo');
        $ben = $this->carer('Ben Dube');
        $cara = $this->carer('Cara Ncube');

        $id = $this->actingAs($admin)->postJson('/api/v1/service-users', [
            'first_name' => 'Ruth',
            'last_name' => 'Chikafu',
            'carer_ids' => [$amy->id, $ben->id],
        ])->assertCreated()
            ->assertJsonCount(2, 'data.carers')
            ->json('data.id');

        $this->assertEmailed($amy, 'carer', "Ruth Chikafu's care team");
        $this->assertEmailed($ben, 'carer');
        $this->assertDatabaseHas('service_user_carer', ['service_user_id' => $id, 'user_id' => $amy->id, 'tenant_id' => $this->tenant->id]);

        // Keep Amy, drop Ben, add Cara: only Cara is emailed.
        $this->actingAs($admin)->patchJson("/api/v1/service-users/{$id}", ['carer_ids' => [$amy->id, $cara->id]])
            ->assertOk()
            ->assertJsonPath('data.carers.*.name', ['Amy Moyo', 'Cara Ncube']);

        $this->assertEmailed($cara, 'carer');
        Notification::assertSentToTimes($amy, AssignmentNotification::class, 1);
        Notification::assertSentToTimes($ben, AssignmentNotification::class, 1);

        // An edit that doesn't send carer_ids leaves the team alone.
        $this->actingAs($admin)->patchJson("/api/v1/service-users/{$id}", ['phone' => '0771 000 000'])
            ->assertOk()
            ->assertJsonCount(2, 'data.carers');
    }

    public function test_carers_must_be_staff_in_the_same_tenant(): void
    {
        $admin = $this->userWithRole('Organization Admin');
        $notStaff = $this->staff('Family Member');
        $otherTenant = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);
        $outsider = User::factory()->create(['tenant_id' => $otherTenant->id]);
        StaffProfile::create(['tenant_id' => $otherTenant->id, 'user_id' => $outsider->id]);
        $serviceUser = ServiceUser::create(['tenant_id' => $this->tenant->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu']);

        $this->actingAs($admin)->patchJson("/api/v1/service-users/{$serviceUser->id}", ['carer_ids' => [$notStaff->id, $outsider->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['carer_ids.0', 'carer_ids.1']);

        Notification::assertNothingSent();
    }
}
