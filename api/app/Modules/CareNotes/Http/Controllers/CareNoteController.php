<?php

namespace App\Modules\CareNotes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CareNotes\Http\Requests\StoreCareNoteRequest;
use App\Modules\CareNotes\Http\Resources\CareNoteResource;
use App\Modules\CareNotes\Models\CareNote;
use App\Modules\ServiceUsers\Models\ServiceUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CareNoteController extends Controller
{
    /**
     * Roles that can remove a note recorded by someone else — everyone can
     * remove their own. Mirrors the tier that can force-close a duty period
     * or reassign a visit: people accountable for a carer's work, not the
     * whole staff roster.
     */
    private const CAN_MODERATE = [
        'Organization Owner',
        'Organization Admin',
        'Branch Manager',
        'Care Manager',
    ];

    public function index(Request $request, ServiceUser $serviceUser)
    {
        abort_unless($request->user()->ownsTenant($serviceUser->tenant_id), 403);

        return CareNoteResource::collection(
            $serviceUser->careNotes()->with('author')->orderByDesc('created_at')->get()
        );
    }

    public function store(StoreCareNoteRequest $request, ServiceUser $serviceUser)
    {
        $file = $request->file('audio');
        $storedName = Str::uuid()->toString().'.'.($file->getClientOriginalExtension() ?: 'webm');
        $path = $file->storeAs("care-notes/{$serviceUser->tenant_id}/{$serviceUser->id}", $storedName, 'local');

        $careNote = $serviceUser->careNotes()->create([
            'tenant_id' => $serviceUser->tenant_id,
            'visit_id' => $request->validated('visit_id'),
            'author_id' => $request->user()->id,
            'caption' => $request->validated('caption'),
            'audio_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'duration_seconds' => $request->validated('duration_seconds'),
        ]);

        return new CareNoteResource($careNote->load('author'));
    }

    /**
     * Streamed inline (not force-downloaded) so the frontend's <audio>
     * element can play it directly.
     */
    public function audio(Request $request, CareNote $careNote)
    {
        abort_unless($request->user()->ownsTenant($careNote->tenant_id), 403);
        abort_unless(Storage::disk('local')->exists($careNote->audio_path), 404);

        return Storage::disk('local')->response(
            $careNote->audio_path,
            null,
            $careNote->mime_type ? ['Content-Type' => $careNote->mime_type] : [],
        );
    }

    public function destroy(Request $request, CareNote $careNote)
    {
        $user = $request->user();

        abort_unless(
            $user->ownsTenant($careNote->tenant_id)
                && ($user->id === $careNote->author_id || $user->hasAnyRole(self::CAN_MODERATE)),
            403
        );

        Storage::disk('local')->delete($careNote->audio_path);
        $careNote->delete();

        return response()->noContent();
    }
}
