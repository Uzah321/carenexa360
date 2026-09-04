<?php

namespace App\Modules\Documents\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Documents\Http\Requests\StoreDocumentRequest;
use App\Modules\Documents\Http\Resources\DocumentResource;
use App\Modules\Documents\Models\Document;
use App\Modules\ServiceUsers\Models\ServiceUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    public function index(Request $request, ServiceUser $serviceUser)
    {
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $serviceUser->tenant_id,
            403
        );

        return DocumentResource::collection(
            $serviceUser->documents()->with('uploadedBy')->orderByDesc('created_at')->get()
        );
    }

    public function store(StoreDocumentRequest $request, ServiceUser $serviceUser)
    {
        $file = $request->file('file');
        $storedName = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs("documents/{$serviceUser->tenant_id}/{$serviceUser->id}", $storedName, 'local');

        $document = $serviceUser->documents()->create([
            'tenant_id' => $serviceUser->tenant_id,
            'category' => $request->validated('category'),
            'original_filename' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'version' => 1,
            'uploaded_by' => $request->user()->id,
            'expiry_date' => $request->validated('expiry_date'),
            'visible_to_family' => $request->validated('visible_to_family', false),
        ]);

        return new DocumentResource($document->load('uploadedBy'));
    }

    public function download(Request $request, Document $document)
    {
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $document->tenant_id,
            403
        );

        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->original_filename);
    }

    public function destroy(Request $request, Document $document)
    {
        abort_unless($request->user()->tenant_id === $document->tenant_id, 403);

        Storage::disk('local')->delete($document->path);
        $document->delete();

        return response()->noContent();
    }
}
