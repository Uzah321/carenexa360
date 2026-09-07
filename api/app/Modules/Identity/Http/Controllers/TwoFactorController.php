<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Http\Requests\ConfirmTwoFactorRequest;
use App\Modules\Identity\Http\Requests\DisableTwoFactorRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    /**
     * Generates a new secret and returns everything needed to enroll an
     * authenticator app. Deliberately doesn't enable 2FA yet — confirm()
     * does that, once the user proves the app is actually working — so
     * starting setup and abandoning it can never lock someone out.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        abort_if($user->hasTwoFactorEnabled(), 409, 'Two-factor authentication is already enabled.');

        // Idempotent while setup is pending — a double-submit (a retried
        // request, a double-click, React StrictMode's double-effect in dev)
        // must not silently swap out the secret already shown/scanned for a
        // new one, or a correctly-entered code would fail against whichever
        // request's secret actually ended up persisted last.
        $secret = $user->mfa_secret ?? (new Google2FA)->generateSecretKey();

        $user->forceFill(['mfa_secret' => $secret])->save();

        return response()->json([
            'secret' => $secret,
            'otpauth_url' => sprintf(
                'otpauth://totp/%s:%s?secret=%s&issuer=%s',
                rawurlencode(config('app.name')),
                rawurlencode($user->email),
                $secret,
                rawurlencode(config('app.name')),
            ),
        ]);
    }

    public function confirm(ConfirmTwoFactorRequest $request)
    {
        $user = $request->user();

        abort_if($user->hasTwoFactorEnabled(), 409, 'Two-factor authentication is already enabled.');
        abort_if(! $user->mfa_secret, 422, 'Start setup before confirming a code.');

        $google2fa = new Google2FA;

        if (! $google2fa->verifyKey($user->mfa_secret, $request->validated('code'))) {
            throw ValidationException::withMessages([
                'code' => ['That code is incorrect or has expired — try the next one from your authenticator app.'],
            ]);
        }

        $user->forceFill(['mfa_enabled_at' => now()])->save();

        return response()->json(['mfa_enabled' => true]);
    }

    public function destroy(DisableTwoFactorRequest $request)
    {
        $request->user()->forceFill([
            'mfa_secret' => null,
            'mfa_enabled_at' => null,
        ])->save();

        return response()->noContent();
    }
}
