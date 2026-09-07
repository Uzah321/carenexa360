<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    protected function stateful(): static
    {
        return $this->withHeader('Origin', 'http://localhost:5173');
    }

    public function test_no_timeout_configured_never_logs_out_an_idle_session(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->stateful()
            ->actingAs($user)
            ->withSession(['last_activity_at' => now()->subDay()->timestamp])
            ->getJson('/api/v1/auth/me');

        $response->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_idle_past_the_configured_timeout_logs_the_user_out(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'country' => 'Zimbabwe',
            'settings' => ['session_timeout_minutes' => 15],
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->stateful()
            ->actingAs($user)
            ->withSession(['last_activity_at' => now()->subMinutes(20)->timestamp])
            ->getJson('/api/v1/auth/me');

        $response->assertUnauthorized()->assertJsonPath('message', 'Your session has expired due to inactivity.');
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_activity_within_the_configured_timeout_stays_logged_in(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'country' => 'Zimbabwe',
            'settings' => ['session_timeout_minutes' => 15],
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->stateful()
            ->actingAs($user)
            ->withSession(['last_activity_at' => now()->subMinutes(5)->timestamp])
            ->getJson('/api/v1/auth/me');

        $response->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_first_request_with_no_recorded_activity_yet_is_not_logged_out(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'country' => 'Zimbabwe',
            'settings' => ['session_timeout_minutes' => 15],
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->stateful()->actingAs($user)->getJson('/api/v1/auth/me');

        $response->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_token_request_with_no_session_is_unaffected(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'country' => 'Zimbabwe',
            'settings' => ['session_timeout_minutes' => 15],
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        // No stateful() Origin header and no withSession() — Sanctum never
        // attaches the session middleware to a request like this, so the
        // middleware must not try to touch $request->session() at all.
        $response = $this->actingAs($user)->getJson('/api/v1/auth/me');

        $response->assertOk();
    }
}
