<?php

namespace App\Modules\MedicalHistory\Support;

use App\Modules\ServiceUsers\Models\ServiceUser;

/**
 * A client's allergies, diagnoses, medical conditions and disabilities tag
 * lists are a summary of their medical history, not a second place to
 * record it. They're still what the Today page, printed care plans, reports
 * and exports read, so they're kept here as a copy of what's current in
 * the history — rebuilt whenever an entry changes.
 */
class MedicalSummary
{
    public static function sync(ServiceUser $serviceUser): void
    {
        $current = $serviceUser->medicalHistory()
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'resolved'))
            ->orderBy('id')
            ->get(['category', 'name']);

        $names = fn (array $categories) => $current->whereIn('category', $categories)
            ->pluck('name')
            ->unique(fn ($name) => mb_strtolower($name))
            ->values()
            ->all();

        // The history keeps diagnoses and conditions as one list.
        $conditions = $names(['condition', 'mental_health']);
        $allergies = $names(['allergy']);

        $serviceUser->update([
            'allergies' => $allergies,
            'diagnoses' => $conditions,
            'medical_conditions' => $conditions,
            'disabilities' => $names(['disability']),
            // A recorded allergy and "no known allergies" can't both stand.
            ...($allergies ? ['no_known_allergies' => false] : []),
        ]);
    }
}
