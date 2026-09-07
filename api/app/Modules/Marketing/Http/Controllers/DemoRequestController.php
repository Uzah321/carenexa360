<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Http\Requests\StoreDemoRequestRequest;
use App\Modules\Marketing\Http\Resources\DemoRequestResource;
use App\Modules\Marketing\Models\DemoRequest;
use App\Notifications\DemoRequestReceivedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class DemoRequestController extends Controller
{
    public function store(StoreDemoRequestRequest $request)
    {
        $demoRequest = DemoRequest::create($request->validated());

        // The request is already saved either way — a bad SMTP config or a
        // network blip notifying the sales inbox must not turn into a 500
        // for someone who just filled out a public marketing form.
        try {
            Notification::route('mail', config('app.demo_requests_email'))
                ->notify(new DemoRequestReceivedNotification($demoRequest));
        } catch (\Throwable $e) {
            Log::warning('Failed to send demo-request notification email.', [
                'demo_request_id' => $demoRequest->id,
                'error' => $e->getMessage(),
            ]);
        }

        return (new DemoRequestResource($demoRequest))->response()->setStatusCode(201);
    }
}
