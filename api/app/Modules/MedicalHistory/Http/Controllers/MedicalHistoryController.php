<?php

namespace App\Modules\MedicalHistory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MedicalHistory\Http\Requests\SaveMedicalHistoryEntryRequest;
use App\Modules\MedicalHistory\Http\Resources\MedicalHistoryEntryResource;
use App\Modules\MedicalHistory\Models\MedicalHistoryEntry;
use App\Modules\MedicalHistory\Support\MedicalHistoryRoles;
use App\Modules\ServiceUsers\Http\Resources\ServiceUserResource;
use App\Modules\ServiceUsers\Models\ServiceUser;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MedicalHistoryController extends Controller
{
    public function index(Request $request, ServiceUser $serviceUser)
    {
        abort_unless($request->user()->ownsTenant($serviceUser->tenant_id), 403);

        return MedicalHistoryEntryResource::collection(
            $serviceUser->medicalHistory()
                ->with('recorder')
                ->orderByRaw('started_on DESC NULLS LAST')
                ->orderByDesc('id')
                ->get()
        );
    }

    public function store(SaveMedicalHistoryEntryRequest $request, ServiceUser $serviceUser)
    {
        abort_unless($request->user()->ownsTenant($serviceUser->tenant_id), 403);

        $entry = $serviceUser->medicalHistory()->create([
            ...MedicalHistoryEntry::forCategory($request->validated()),
            'tenant_id' => $serviceUser->tenant_id,
            'recorded_by' => $request->user()->id,
        ]);

        return (new MedicalHistoryEntryResource($entry->load('recorder')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(SaveMedicalHistoryEntryRequest $request, MedicalHistoryEntry $medicalHistoryEntry)
    {
        abort_unless($request->user()->ownsTenant($medicalHistoryEntry->tenant_id), 403);

        $medicalHistoryEntry->update(MedicalHistoryEntry::forCategory([
            ...$medicalHistoryEntry->only(MedicalHistoryEntry::EDITABLE),
            ...$request->validated(),
        ]));

        return new MedicalHistoryEntryResource($medicalHistoryEntry->fresh()->load('recorder'));
    }

    /**
     * For an entry recorded in error. Something that's no longer true — a
     * condition that has cleared up — is marked resolved instead, so it
     * stays part of the history.
     */
    public function destroy(Request $request, MedicalHistoryEntry $medicalHistoryEntry)
    {
        $user = $request->user();

        abort_unless(
            $user->ownsTenant($medicalHistoryEntry->tenant_id)
                && ($user->id === $medicalHistoryEntry->recorded_by || $user->hasAnyRole(MedicalHistoryRoles::CLINICAL_LEADS)),
            403
        );

        $medicalHistoryEntry->delete();

        return response()->noContent();
    }

    /**
     * Signs the history off as complete and up to date, as of now. Refused
     * while allergy status is unknown — a history can't be complete without
     * it, and it's what medication safety depends on.
     */
    public function review(Request $request, ServiceUser $serviceUser)
    {
        abort_unless($request->user()->ownsTenant($serviceUser->tenant_id), 403);
        abort_unless($request->user()->hasAnyRole(MedicalHistoryRoles::CLINICAL_LEADS), 403);

        if (! $serviceUser->allergyStatusKnown()) {
            throw ValidationException::withMessages([
                'allergies' => 'Record the client\'s allergies, or confirm they have no known allergies, before signing the history off.',
            ]);
        }

        $serviceUser->update([
            'medical_history_reviewed_at' => now(),
            'medical_history_reviewed_by' => $request->user()->id,
        ]);

        return new ServiceUserResource($serviceUser->fresh()->load(['carers', 'medicalHistoryReviewer']));
    }
}
