<?php

namespace App\Modules\Assessments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Assessments\Http\Requests\StoreAssessmentTemplateRequest;
use App\Modules\Assessments\Http\Resources\AssessmentTemplateResource;
use App\Modules\Assessments\Models\AssessmentTemplate;
use Illuminate\Http\Request;

class AssessmentTemplateController extends Controller
{
    public function index(Request $request)
    {
        return AssessmentTemplateResource::collection(
            AssessmentTemplate::orderBy('name')->get()
        );
    }

    public function store(StoreAssessmentTemplateRequest $request)
    {
        $template = AssessmentTemplate::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
        ]);

        return (new AssessmentTemplateResource($template->fresh()))
            ->response()
            ->setStatusCode(201);
    }
}
