<?php

namespace App\Modules\Assessments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Assessments\Http\Requests\StoreAssessmentResponseRequest;
use App\Modules\Assessments\Http\Resources\AssessmentResponseResource;
use App\Modules\Assessments\Models\AssessmentResponse;
use App\Modules\ServiceUsers\Models\ServiceUser;
use Illuminate\Http\Request;

class AssessmentResponseController extends Controller
{
    public function index(Request $request, ServiceUser $serviceUser)
    {
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $serviceUser->tenant_id,
            403
        );

        return AssessmentResponseResource::collection(
            $serviceUser->assessmentResponses()
                ->with(['template', 'completedBy'])
                ->orderByDesc('created_at')
                ->get()
        );
    }

    public function store(StoreAssessmentResponseRequest $request, ServiceUser $serviceUser)
    {
        $status = $request->validated('status', 'completed');

        $response = $serviceUser->assessmentResponses()->create([
            'tenant_id' => $serviceUser->tenant_id,
            'assessment_template_id' => $request->validated('assessment_template_id'),
            'answers' => $request->validated('answers'),
            'completed_by' => $request->user()->id,
            'completed_at' => $status === 'completed' ? now() : null,
            'status' => $status,
        ]);

        return new AssessmentResponseResource($response->load(['template', 'completedBy']));
    }

    public function show(Request $request, AssessmentResponse $assessmentResponse)
    {
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $assessmentResponse->tenant_id,
            403
        );

        return new AssessmentResponseResource($assessmentResponse->load(['template', 'completedBy']));
    }
}
