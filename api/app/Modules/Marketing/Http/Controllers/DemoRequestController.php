<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Http\Requests\StoreDemoRequestRequest;
use App\Modules\Marketing\Http\Resources\DemoRequestResource;
use App\Modules\Marketing\Models\DemoRequest;
use App\Modules\Marketing\Support\DemoTenant;
use App\Notifications\DemoRequestConfirmationNotification;
use App\Notifications\DemoRequestReceivedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class DemoRequestController extends Controller
{
    public function store(StoreDemoRequestRequest $request)
    {
        $demoRequest = DemoRequest::create($request->validated());

        // The request is already saved either way — a bad SMTP config or a
        // network blip notifying the sales inbox (or the requester) must
        // not turn into a 500 for someone who just filled out a public
        // marketing form. The demo login is also returned directly in the
        // response below, so a failure here only costs the emailed copy,
        // not their only way to reach it.
        try {
            Notification::route('mail', config('app.demo_requests_email'))
                ->notify(new DemoRequestReceivedNotification($demoRequest));
        } catch (\Throwable $e) {
            Log::warning('Failed to send demo-request notification email.', [
                'demo_request_id' => $demoRequest->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            Notification::route('mail', $demoRequest->email)
                ->notify(new DemoRequestConfirmationNotification($demoRequest));
        } catch (\Throwable $e) {
            Log::warning('Failed to send demo-request confirmation email.', [
                'demo_request_id' => $demoRequest->id,
                'error' => $e->getMessage(),
            ]);
        }

        return (new DemoRequestResource($demoRequest))
            ->additional(['demo_login' => [
                'email' => DemoTenant::LOGIN_EMAIL,
                'password' => DemoTenant::LOGIN_PASSWORD,
                'url' => DemoTenant::LOGIN_URL,
            ]])
            ->response()
            ->setStatusCode(201);
    }
}
