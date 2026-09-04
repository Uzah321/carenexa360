<?php

namespace App\Modules\Observations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Observations\Http\Resources\ClinicalAlertResource;
use App\Modules\Observations\Models\ClinicalAlert;
use App\Modules\ServiceUsers\Models\ServiceUser;
use Illuminate\Http\Request;

class ClinicalAlertController extends Controller
{
    public function index(Request $request, ServiceUser $serviceUser)
    {
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $serviceUser->tenant_id,
            403
        );

        return ClinicalAlertResource::collection(
            ClinicalAlert::where('service_user_id', $serviceUser->id)
                ->with('acknowledgedBy')
                ->orderByDesc('created_at')
                ->get()
        );
    }

    public function acknowledge(Request $request, ClinicalAlert $alert)
    {
        abort_unless($request->user()->tenant_id === $alert->tenant_id, 403);

        $alert->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => $request->user()->id,
        ]);

        return new ClinicalAlertResource($alert->fresh()->load('acknowledgedBy'));
    }
}
