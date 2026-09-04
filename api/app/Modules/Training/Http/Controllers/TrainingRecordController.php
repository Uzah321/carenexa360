<?php

namespace App\Modules\Training\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Training\Http\Requests\StoreTrainingRecordRequest;
use App\Modules\Training\Http\Resources\TrainingRecordResource;
use App\Modules\Training\Models\TrainingCourse;
use App\Modules\Training\Models\TrainingRecord;
use App\Modules\Training\Support\ComplianceRoles;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TrainingRecordController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isComplianceAdmin = $user->hasAnyRole(ComplianceRoles::ALLOWED);

        $records = TrainingRecord::with(['user', 'trainingCourse'])
            ->when(! $isComplianceAdmin, fn ($q) => $q->where('user_id', $user->id))
            ->when($isComplianceAdmin && $request->query('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->orderByDesc('completed_date')
            ->get();

        $status = $request->query('status');
        if ($status) {
            $records = $records->filter(fn (TrainingRecord $record) => $record->status === $status)->values();
        }

        return TrainingRecordResource::collection($records);
    }

    public function store(StoreTrainingRecordRequest $request)
    {
        abort_unless($request->user()->hasAnyRole(ComplianceRoles::ALLOWED), 403);

        $course = TrainingCourse::findOrFail($request->validated('training_course_id'));

        $expiryDate = $request->validated('expiry_date');
        if (! $expiryDate && $course->validity_period_months) {
            $expiryDate = Carbon::parse($request->validated('completed_date'))
                ->addMonths($course->validity_period_months)
                ->toDateString();
        }

        $record = TrainingRecord::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
            'expiry_date' => $expiryDate,
            'recorded_by' => $request->user()->id,
        ]);

        return (new TrainingRecordResource($record->load(['user', 'trainingCourse'])))
            ->response()
            ->setStatusCode(201);
    }
}
