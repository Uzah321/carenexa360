<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Organization\Models\Tenant;
use App\Modules\ServiceUsers\Models\ServiceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTenantWithServiceUser(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $serviceUser = ServiceUser::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'John',
            'last_name' => 'Smith',
        ]);

        return compact('tenant', 'admin', 'serviceUser');
    }

    public function test_tenant_user_can_report_an_incident(): void
    {
        ['admin' => $admin, 'serviceUser' => $serviceUser] = $this->makeTenantWithServiceUser();

        $response = $this->actingAs($admin)->postJson('/api/v1/incidents', [
            'service_user_id' => $serviceUser->id,
            'type' => 'fall',
            'severity' => 'medium',
            'description' => 'Service user slipped in the bathroom.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'reported')
            ->assertJsonPath('data.reported_by', $admin->id);
    }

    public function test_incident_workflow_progresses_through_to_closed(): void
    {
        ['admin' => $admin, 'serviceUser' => $serviceUser] = $this->makeTenantWithServiceUser();

        $incident = Incident::create([
            'tenant_id' => $serviceUser->tenant_id,
            'service_user_id' => $serviceUser->id,
            'type' => 'fall',
            'severity' => 'medium',
            'description' => 'Service user slipped in the bathroom.',
            'status' => 'reported',
            'reported_by' => $admin->id,
        ]);

        $this->actingAs($admin)->patchJson("/api/v1/incidents/{$incident->id}", [
            'status' => 'investigating',
        ])->assertOk()->assertJsonPath('data.status', 'investigating');

        $this->actingAs($admin)->patchJson("/api/v1/incidents/{$incident->id}", [
            'status' => 'corrective_action',
            'corrective_actions' => 'Non-slip mat installed.',
        ])->assertOk()->assertJsonPath('data.status', 'corrective_action');

        $reviewed = $this->actingAs($admin)->patchJson("/api/v1/incidents/{$incident->id}", [
            'status' => 'reviewed',
        ]);
        $reviewed->assertOk()->assertJsonPath('data.status', 'reviewed');
        $this->assertNotNull($incident->fresh()->reviewed_at);
        $this->assertSame($admin->id, $incident->fresh()->reviewed_by);

        $closed = $this->actingAs($admin)->patchJson("/api/v1/incidents/{$incident->id}", [
            'status' => 'closed',
        ]);
        $closed->assertOk()->assertJsonPath('data.status', 'closed');
        $this->assertNotNull($incident->fresh()->closed_at);
    }

    public function test_tenant_user_can_edit_incident_report_details(): void
    {
        ['admin' => $admin, 'serviceUser' => $serviceUser] = $this->makeTenantWithServiceUser();

        $incident = Incident::create([
            'tenant_id' => $serviceUser->tenant_id,
            'service_user_id' => $serviceUser->id,
            'type' => 'fall',
            'severity' => 'medium',
            'description' => 'Service user slipped in the bathroom.',
            'status' => 'reported',
            'reported_by' => $admin->id,
        ]);

        $this->actingAs($admin)->patchJson("/api/v1/incidents/{$incident->id}", [
            'type' => 'injury',
            'description' => 'Service user slipped in the bathroom and bruised their arm.',
            'immediate_action' => 'Applied ice pack, monitored for 30 minutes.',
        ])->assertOk()
            ->assertJsonPath('data.type', 'injury')
            ->assertJsonPath('data.description', 'Service user slipped in the bathroom and bruised their arm.')
            ->assertJsonPath('data.immediate_action', 'Applied ice pack, monitored for 30 minutes.');
    }

    public function test_tenant_user_cannot_view_another_tenants_incident(): void
    {
        ['serviceUser' => $serviceUserA, 'admin' => $adminA] = $this->makeTenantWithServiceUser();

        $incidentA = Incident::create([
            'tenant_id' => $serviceUserA->tenant_id,
            'service_user_id' => $serviceUserA->id,
            'type' => 'fall',
            'severity' => 'low',
            'description' => 'Minor slip.',
            'status' => 'reported',
            'reported_by' => $adminA->id,
        ]);

        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAs($userB)
            ->getJson("/api/v1/incidents/{$incidentA->id}")
            ->assertForbidden();
    }
}
