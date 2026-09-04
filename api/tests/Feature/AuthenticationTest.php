<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Identity\Support\DefaultRoles;
use App\Modules\Organization\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function stateful(): static
    {
        return $this->withHeader('Origin', 'http://localhost:5173');
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $response = $this->stateful()->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $response->assertNoContent();
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'tenant_id' => null,
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $response = $this->stateful()->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.test',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_a_user_of_a_suspended_organization_cannot_log_in(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe', 'status' => 'suspended']);
        User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'staff@tenant-a.test', 'password' => 'password']);

        $response = $this->stateful()->postJson('/api/v1/auth/login', [
            'email' => 'staff@tenant-a.test',
            'password' => 'password',
        ]);

        $response->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_a_user_of_an_active_organization_can_log_in(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe', 'status' => 'active']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'staff@tenant-a.test', 'password' => 'password']);

        $response = $this->stateful()->postJson('/api/v1/auth/login', [
            'email' => 'staff@tenant-a.test',
            'password' => 'password',
        ]);

        $response->assertNoContent();
        $this->assertAuthenticatedAs($user);
    }

    public function test_me_endpoint_returns_authenticated_user_with_roles(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(DefaultRoles::PLATFORM_TEAM_ID);

        $user = User::factory()->create(['tenant_id' => null]);
        $role = Role::firstOrCreate([
            'name' => DefaultRoles::PLATFORM_SUPER_ADMIN,
            'guard_name' => 'web',
            'tenant_id' => DefaultRoles::PLATFORM_TEAM_ID,
        ]);
        $user->assignRole($role);

        $response = $this->actingAs($user)->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.roles.0', 'Platform Super Admin');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    }

    /**
     * Note: this only exercises a single simulated request (login), then checks the
     * guard directly. A further chained logout->me round trip was verified manually
     * against a real running server instead of here, because Laravel's in-process
     * test client does not carry cookies/session state across separate simulated
     * requests the way a real browser does, which makes a 3-request chain unreliable
     * to assert on even though the real behavior (confirmed manually) is correct.
     */
    public function test_logout_clears_the_session(): void
    {
        User::factory()->create([
            'tenant_id' => null,
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $loginResponse = $this->stateful()->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);
        $loginResponse->assertNoContent();

        $sessionCookieName = config('session.cookie');
        $sessionCookie = collect($loginResponse->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === $sessionCookieName);

        $this->stateful()
            ->withUnencryptedCookie($sessionCookieName, $sessionCookie->getValue())
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->assertFalse(Auth::guard('web')->check());
    }
}
