<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Requests\RegisterRequest;
use App\Modules\Identity\Http\Requests\TwoFactorChallengeRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Identity\Support\DefaultRoles;
use App\Modules\Organization\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AuthController extends Controller
{
    /**
     * Self-service sign-up from the marketing site's "Get Started" flow —
     * creates a brand-new tenant (on a trial plan) plus its first user, who
     * becomes that tenant's Organization Owner. TenantObserver seeds the
     * tenant's roles as soon as it's created, so "Organization Owner"
     * already exists by the time assignRole() below runs.
     */
    public function register(RegisterRequest $request)
    {
        $tenant = Tenant::create([
            'name' => $request->validated('organization_name'),
            'slug' => $this->uniqueSlug($request->validated('organization_name')),
            'country' => $request->validated('country'),
            'timezone' => 'UTC',
            'currency' => 'GBP',
            'locale' => 'en',
            'status' => 'trial',
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
        ]);

        // No 'tenant' middleware has run for this request (it's public, and
        // there was no tenant to resolve until the line above), so the
        // ambient Spatie team context is still unset — set it explicitly or
        // assignRole() would file the role under the wrong team.
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $role = Role::where('name', DefaultRoles::TENANT_ROLES[0])
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();
        $user->assignRole($role);

        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return response()->noContent();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'organization';
        $slug = $base;
        $suffix = 1;

        while (Tenant::where('slug', $slug)->exists()) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials, remember: false)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        $user = $request->user();

        // Suspending an organization (Organizations page, platform admin
        // only) must actually block sign-in, not just change a status label
        // shown on a list — check it before the session is established.
        if ($user->tenant?->status === 'suspended') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            throw ValidationException::withMessages([
                'email' => ['This organization\'s account has been suspended. Contact your platform administrator.'],
            ]);
        }

        // A 2FA-enabled account doesn't get a session yet — Auth::attempt()
        // above already established one, so undo that (without invalidating
        // the session itself, unlike the suspended-tenant branch above: the
        // pending marker below needs to survive into the next request) and
        // wait for a verified code before this login actually completes.
        if ($user->hasTwoFactorEnabled()) {
            Auth::guard('web')->logout();
            $request->session()->put('mfa_pending_user_id', $user->id);

            return response()->json(['two_factor_required' => true]);
        }

        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->noContent();
    }

    public function twoFactorChallenge(TwoFactorChallengeRequest $request)
    {
        $userId = $request->session()->get('mfa_pending_user_id');
        $user = $userId ? User::find($userId) : null;

        if (! $user || ! $user->mfa_secret || ! (new Google2FA)->verifyKey($user->mfa_secret, $request->validated('code'))) {
            throw ValidationException::withMessages([
                'code' => ['That code is incorrect or has expired — try the next one from your authenticator app.'],
            ]);
        }

        $request->session()->forget('mfa_pending_user_id');
        Auth::login($user);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->noContent();
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request)
    {
        return new UserResource($request->user());
    }
}
