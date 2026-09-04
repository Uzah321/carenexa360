<?php

namespace App\Modules\Compliance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compliance\Http\Requests\StoreComplianceDocumentRequest;
use App\Modules\Compliance\Models\ComplianceRequirement;
use App\Modules\Documents\Http\Resources\DocumentResource;
use App\Modules\Training\Support\ComplianceRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ComplianceDocumentController extends Controller
{
    public function index(Request $request, ComplianceRequirement $complianceRequirement)
    {
        abort_unless($request->user()->hasAnyRole(ComplianceRoles::ALLOWED), 403);
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $complianceRequirement->tenant_id,
            403
        );

        return DocumentResource::collection(
            $complianceRequirement->documents()->with('uploadedBy')->orderByDesc('created_at')->get()
        );
    }

    public function store(StoreComplianceDocumentRequest $request, ComplianceRequirement $complianceRequirement)
    {
        $file = $request->file('file');
        $storedName = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs(
            "documents/{$complianceRequirement->tenant_id}/compliance/{$complianceRequirement->id}",
            $storedName,
            'local',
        );

        $document = $complianceRequirement->documents()->create([
            'tenant_id' => $complianceRequirement->tenant_id,
            'category' => $request->validated('category'),
            'original_filename' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'version' => 1,
            'uploaded_by' => $request->user()->id,
            'expiry_date' => $request->validated('expiry_date'),
        ]);

        return new DocumentResource($document->load('uploadedBy'));
    }
}
