<?php

namespace App\Modules\CarePlanning\Support;

use App\Modules\CarePlanning\Models\CarePlan;
use App\Modules\Organization\Support\TenantSettings;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Support\Time\TenantClock;
use Carbon\Carbon;

/**
 * Where a client is on the organisation's care pathway — referral,
 * assessment, care plan, first review, then regular reviews — measured
 * against the timescales set in System Settings → Care Pathway.
 *
 * Each stage is: done (on time or late), due (by a date), or overdue.
 */
class CarePathway
{
    /**
     * @return array{stages: list<array{key: string, label: string, status: string, due: ?string, done: ?string}>, overdue: int}
     */
    public static function for(ServiceUser $serviceUser): array
    {
        $tenantId = $serviceUser->tenant_id;
        $pathway = TenantSettings::for($tenantId, 'care_pathway');
        $today = TenantClock::today($tenantId);
        $local = fn (?\DateTimeInterface $at) => $at ? TenantClock::local($tenantId, $at)->toDateString() : null;

        $referred = $local($serviceUser->created_at);
        $plans = $serviceUser->carePlans()->with('riskAssessments')->orderBy('version')->get();
        $firstPlan = $plans->first();
        $activePlan = $plans->firstWhere('status', 'active');
        $firstAssessment = $serviceUser->assessmentResponses()
            ->whereNotNull('completed_at')
            ->orderBy('completed_at')
            ->value('completed_at');

        $addDays = fn (?string $date, int $days) => $date ? Carbon::parse($date)->addDays($days)->toDateString() : null;
        $addMonths = fn (?string $date, int $months) => $date ? Carbon::parse($date)->addMonthsNoOverflow($months)->toDateString() : null;

        $planDone = $local($firstPlan?->created_at);
        // Any version after the first is a review of the plan.
        $firstReviewDone = $local($plans->firstWhere('version', '>', 1)?->created_at);
        $lastReview = $local($plans->last()?->created_at);

        $stages = [
            self::stage('referral', 'Referral received', $referred, $referred, $today),
            self::stage(
                'assessment',
                'Initial assessment',
                $addDays($referred, (int) $pathway['assessment_within_days']),
                $local($firstAssessment ? Carbon::parse($firstAssessment) : null),
                $today,
            ),
            self::stage('care_plan', 'Care plan agreed', $addDays($referred, (int) $pathway['care_plan_within_days']), $planDone, $today),
            self::stage('first_review', 'First review', $addDays($planDone, (int) $pathway['first_review_within_weeks'] * 7), $firstReviewDone, $today),
        ];

        // Ongoing reviews only start once the first review has happened.
        if ($firstReviewDone) {
            $stages[] = self::stage(
                'next_review',
                'Next scheduled review',
                $addMonths($lastReview, (int) $pathway['review_interval_months']),
                null,
                $today,
            );
        }

        $riskDue = self::nextRiskReview($activePlan, (int) $pathway['risk_review_interval_months'], $local);
        if ($riskDue) {
            $stages[] = self::stage('risk_review', 'Next risk review', $riskDue, null, $today);
        }

        return [
            'stages' => $stages,
            'overdue' => count(array_filter($stages, fn ($s) => $s['status'] === 'overdue')),
        ];
    }

    /** The earliest review date set on the plan's risks, or the plan date plus the risk review interval. */
    private static function nextRiskReview(?CarePlan $plan, int $months, callable $local): ?string
    {
        if (! $plan || $plan->riskAssessments->isEmpty()) {
            return null;
        }
        $set = $plan->riskAssessments->pluck('review_date')->filter()->map(fn ($d) => $d->toDateString())->sort()->first();

        return $set ?? Carbon::parse($local($plan->created_at))->addMonthsNoOverflow($months)->toDateString();
    }

    private static function stage(string $key, string $label, ?string $due, ?string $done, string $today): array
    {
        $status = match (true) {
            $done !== null && ($due === null || $done <= $due) => 'done',
            $done !== null => 'done_late',
            $due === null => 'waiting',
            $due < $today => 'overdue',
            default => 'due',
        };

        return compact('key', 'label', 'status', 'due', 'done');
    }
}
