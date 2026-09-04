<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
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
