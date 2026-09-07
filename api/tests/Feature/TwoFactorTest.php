<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(): User
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);

        return User::factory()->create(['tenant_id' => $tenant->id]);
    }

    public function test_a_user_can_enable_two_factor_authentication(): void
    {
        $user = $this->makeUser();

        $setup = $this->actingAs($user)->postJson('/api/v1/account/two-factor');
        $setup->assertOk()->assertJsonStructure(['secret', 'otpauth_url']);

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());

        $secret = $setup->json('secret');
        $code = (new Google2FA)->getCurrentOtp($secret);

        $confirm = $this->actingAs($user)->postJson('/api/v1/account/two-factor/confirm', ['code' => $code]);
        $confirm->assertOk()->assertJsonPath('mfa_enabled', true);

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_starting_setup_twice_before_confirming_returns_the_same_secret(): void
    {
        $user = $this->makeUser();

        $first = $this->actingAs($user)->postJson('/api/v1/account/two-factor');
        $second = $this->actingAs($user)->postJson('/api/v1/account/two-factor');

        $first->assertOk();
        $second->assertOk();
        $this->assertSame($first->json('secret'), $second->json('secret'));
    }

    public function test_confirming_with_an_invalid_code_does_not_enable_it(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->postJson('/api/v1/account/two-factor');

        $response = $this->actingAs($user)->postJson('/api/v1/account/two-factor/confirm', ['code' => '000000']);

        $response->assertUnprocessable();
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_setup_cannot_be_started_again_once_enabled(): void
    {
        $user = $this->makeUser();
        $secret = (new Google2FA)->generateSecretKey();
        $user->forceFill(['mfa_secret' => $secret, 'mfa_enabled_at' => now()])->save();

        $this->actingAs($user)->postJson('/api/v1/account/two-factor')->assertStatus(409);
    }

    public function test_a_user_can_disable_two_factor_with_their_current_password(): void
    {
        $user = $this->makeUser();
        $user->forceFill([
            'mfa_secret' => (new Google2FA)->generateSecretKey(),
            'mfa_enabled_at' => now(),
        ])->save();

        $response = $this->actingAs($user)->deleteJson('/api/v1/account/two-factor', [
            'current_password' => 'password',
        ]);

        $response->assertNoContent();
        $fresh = $user->fresh();
        $this->assertFalse($fresh->hasTwoFactorEnabled());
        $this->assertNull($fresh->mfa_secret);
    }

    public function test_disabling_two_factor_requires_the_correct_password(): void
    {
        $user = $this->makeUser();
        $user->forceFill([
            'mfa_secret' => (new Google2FA)->generateSecretKey(),
            'mfa_enabled_at' => now(),
        ])->save();

        $response = $this->actingAs($user)->deleteJson('/api/v1/account/two-factor', [
            'current_password' => 'wrong-password',
        ]);

        $response->assertUnprocessable();
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }
}
