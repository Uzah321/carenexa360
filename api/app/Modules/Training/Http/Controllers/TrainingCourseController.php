<?php

namespace App\Modules\Training\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Training\Http\Requests\StoreTrainingCourseRequest;
use App\Modules\Training\Http\Resources\TrainingCourseResource;
use App\Modules\Training\Models\TrainingCourse;
use App\Modules\Training\Support\ComplianceRoles;
use Illuminate\Http\Request;

class TrainingCourseController extends Controller
{
    public function index(Request $request)
    {
        return TrainingCourseResource::collection(TrainingCourse::orderBy('name')->get());
    }

    public function store(StoreTrainingCourseRequest $request)
    {
        abort_unless($request->user()->hasAnyRole(ComplianceRoles::ALLOWED), 403);

        $course = TrainingCourse::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
        ]);

        return (new TrainingCourseResource($course))
            ->response()
            ->setStatusCode(201);
    }
}
