<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Documents\Models\Document;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Organization\Models\Tenant;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\ServiceUsers\Models\ServiceUserContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FamilyPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function makeFamilyMember(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $serviceUser = ServiceUser::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Ruth',
            'last_name' => 'Chikafu',
        ]);
        $familyUser = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Mary Chikafu',
            'email' => 'mary@family.test',
            'password' => 'password123',
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $role = Role::where(['name' => 'Family Member', 'tenant_id' => $tenant->id])->firstOrFail();
        $familyUser->assignRole($role);
        $contact = ServiceUserContact::create([
            'tenant_id' => $tenant->id,
            'service_user_id' => $serviceUser->id,
            'user_id' => $familyUser->id,
            'type' => 'family',
            'name' => 'Mary Chikafu',
        ]);

        return compact('tenant', 'serviceUser', 'familyUser', 'contact');
    }

    public function test_granting_portal_access_creates_a_working_login(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu']);
        $contact = ServiceUserContact::create([
            'tenant_id' => $tenant->id,
            'service_user_id' => $serviceUser->id,
            'type' => 'family',
            'name' => 'Mary Chikafu',
        ]);

        $response = $this->actingAs($admin)->postJson(
            "/api/v1/service-users/{$serviceUser->id}/contacts/{$contact->id}/grant-portal-access",
            ['email' => 'mary@family.test', 'password' => 'password123'],
        );

        $response->assertOk()->assertJsonPath('data.has_portal_access', true);

        $familyUser = User::where('email', 'mary@family.test')->firstOrFail();
        $this->assertTrue($familyUser->hasRole('Family Member'));

        $this->actingAs($familyUser)
            ->getJson('/api/v1/family-portal')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_family_member_is_blocked_from_every_staff_only_endpoint(): void
    {
        ['familyUser' => $familyUser] = $this->makeFamilyMember();

        $this->actingAs($familyUser)->getJson('/api/v1/service-users')->assertForbidden();
        $this->actingAs($familyUser)->getJson('/api/v1/staff')->assertForbidden();
        $this->actingAs($familyUser)->getJson('/api/v1/visits')->assertForbidden();
    }

    public function test_family_member_can_only_view_their_own_linked_service_user(): void
    {
        ['tenant' => $tenant, 'familyUser' => $familyUser, 'serviceUser' => $ownServiceUser] = $this->makeFamilyMember();
        $otherServiceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'Jane', 'last_name' => 'Doe']);

        $this->actingAs($familyUser)
            ->getJson("/api/v1/family-portal/{$ownServiceUser->id}")
            ->assertOk()
            ->assertJsonPath('service_user.id', $ownServiceUser->id);

        $this->actingAs($familyUser)
            ->getJson("/api/v1/family-portal/{$otherServiceUser->id}")
            ->assertForbidden();
    }

    public function test_family_view_only_shows_documents_flagged_visible_to_family(): void
    {
        ['familyUser' => $familyUser, 'serviceUser' => $serviceUser] = $this->makeFamilyMember();

        $serviceUser->documents()->create([
            'tenant_id' => $serviceUser->tenant_id,
            'category' => 'care-plan-summary',
            'original_filename' => 'shared.pdf',
            'path' => 'documents/shared.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'version' => 1,
            'visible_to_family' => true,
        ]);
        $serviceUser->documents()->create([
            'tenant_id' => $serviceUser->tenant_id,
            'category' => 'safeguarding-evidence',
            'original_filename' => 'private.pdf',
            'path' => 'documents/private.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'version' => 1,
            'visible_to_family' => false,
        ]);

        $response = $this->actingAs($familyUser)->getJson("/api/v1/family-portal/{$serviceUser->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'documents')
            ->assertJsonPath('documents.0.original_filename', 'shared.pdf');
    }

    public function test_family_view_of_incidents_excludes_internal_investigation_fields(): void
    {
        ['familyUser' => $familyUser, 'serviceUser' => $serviceUser, 'tenant' => $tenant] = $this->makeFamilyMember();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);

        Incident::create([
            'tenant_id' => $tenant->id,
            'service_user_id' => $serviceUser->id,
            'type' => 'fall',
            'severity' => 'medium',
            'description' => 'Slipped in the bathroom.',
            'status' => 'investigating',
            'reported_by' => $admin->id,
            'investigation_notes' => 'Internal staff notes not for family.',
            'corrective_actions' => 'Non-slip mat installed.',
        ]);

        $response = $this->actingAs($familyUser)->getJson("/api/v1/family-portal/{$serviceUser->id}");

        $response->assertOk()->assertJsonCount(1, 'incidents');
        $incident = $response->json('incidents.0');
        $this->assertArrayNotHasKey('investigation_notes', $incident);
        $this->assertArrayNotHasKey('corrective_actions', $incident);
        $this->assertSame('Slipped in the bathroom.', $incident['description']);
    }
}
