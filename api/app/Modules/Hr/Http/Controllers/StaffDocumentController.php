<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Documents\Http\Resources\DocumentResource;
use App\Modules\Hr\Http\Requests\StoreStaffDocumentRequest;
use App\Modules\Staff\Models\StaffProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StaffDocumentController extends Controller
{
    public function index(Request $request, StaffProfile $staff)
    {
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $staff->tenant_id,
            403
        );

        return DocumentResource::collection(
            $staff->documents()->with('uploadedBy')->orderByDesc('created_at')->get()
        );
    }

    public function store(StoreStaffDocumentRequest $request, StaffProfile $staff)
    {
        $file = $request->file('file');
        $storedName = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs("documents/{$staff->tenant_id}/staff/{$staff->id}", $storedName, 'local');

        $document = $staff->documents()->create([
            'tenant_id' => $staff->tenant_id,
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
