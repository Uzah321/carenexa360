<?php

namespace App\Modules\Quality\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Quality\Http\Requests\SaveSpotCheckRequest;
use App\Modules\Quality\Http\Resources\SpotCheckResource;
use App\Modules\Quality\Models\SpotCheck;
use App\Modules\Quality\Support\QualityRoles;
use Illuminate\Http\Request;

class SpotCheckController extends Controller
{
    private const RELATIONS = ['staff', 'checkedBy', 'serviceUser'];

    public function index(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(QualityRoles::ALLOWED), 403);

        $checks = SpotCheck::with(self::RELATIONS)
            ->when($request->query('staff_user_id'), fn ($q, $id) => $q->where('staff_user_id', $id))
            ->when($request->query('outcome'), fn ($q, $outcome) => $q->where('outcome', $outcome))
            ->orderByDesc('check_date')
            ->orderByDesc('id')
            ->paginate(20);

        return SpotCheckResource::collection($checks);
    }

    public function store(SaveSpotCheckRequest $request)
    {
        $attributes = $request->validated();

        $check = SpotCheck::create([
            ...$attributes,
            'tenant_id' => $request->user()->tenant_id,
            'checked_by' => $request->user()->id,
            'outcome' => SpotCheck::outcomeFor($attributes['results']),
        ]);

        return (new SpotCheckResource($check->load(self::RELATIONS)))->response()->setStatusCode(201);
    }

    public function show(Request $request, SpotCheck $spotCheck)
    {
        abort_unless($request->user()->hasAnyRole(QualityRoles::ALLOWED), 403);
        abort_unless($request->user()->ownsTenant($spotCheck->tenant_id), 403);

        return new SpotCheckResource($spotCheck->load(self::RELATIONS));
    }

    public function update(SaveSpotCheckRequest $request, SpotCheck $spotCheck)
    {
        abort_unless($request->user()->ownsTenant($spotCheck->tenant_id), 403);

        $attributes = $request->validated();
        if (isset($attributes['results'])) {
            $attributes['outcome'] = SpotCheck::outcomeFor($attributes['results']);
        }

        $spotCheck->update($attributes);

        return new SpotCheckResource($spotCheck->fresh()->load(self::RELATIONS));
    }
}
