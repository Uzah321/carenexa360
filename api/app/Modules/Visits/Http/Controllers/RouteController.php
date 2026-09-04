<?php

namespace App\Modules\Visits\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Visits\Http\Resources\VisitResource;
use App\Modules\Visits\Models\Visit;
use Illuminate\Http\Request;

class RouteController extends Controller
{
    public function show(Request $request)
    {
        $validated = $request->validate([
            'carer_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
        ]);

        $visits = Visit::with('serviceUser')
            ->where('carer_id', $validated['carer_id'])
            ->where('visit_date', $validated['date'])
            ->orderBy('start_time')
            ->get();

        return VisitResource::collection($visits)->additional([
            'stops' => $visits->map(fn (Visit $visit) => [
                'visit_id' => $visit->id,
                'label' => trim("{$visit->serviceUser?->first_name} {$visit->serviceUser?->last_name}"),
                'start_time' => $visit->start_time,
                'latitude' => $visit->serviceUser?->latitude,
                'longitude' => $visit->serviceUser?->longitude,
            ])->filter(fn ($stop) => $stop['latitude'] && $stop['longitude'])->values(),
        ]);
    }
}
