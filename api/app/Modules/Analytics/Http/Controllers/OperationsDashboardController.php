<?php

namespace App\Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Support\AnalyticsRoles;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Compliance\Models\ComplianceRequirement;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Tenant;
use App\Modules\Safeguarding\Models\SafeguardingCase;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\Staff\Models\StaffProfile;
use App\Modules\Training\Models\TrainingRecord;
use App\Modules\Visits\Models\Visit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OperationsDashboardController extends Controller
{
    protected const TREND_WEEKS = 8;

    /**
     * Statuses that represent an invoice actually issued to the client —
     * excludes 'draft' (not yet sent, so not real committed revenue) and
     * 'cancelled' (voided). Used everywhere revenue/billed amounts are
     * computed, so a draft never inflates a revenue figure.
     */
    protected const BILLED_STATUSES = ['sent', 'paid', 'overdue'];

    public function summary(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(AnalyticsRoles::ALLOWED), 403);

        $weekStarts = $this->lastNWeekStarts(self::TREND_WEEKS);

        return response()->json([
            'headline' => $this->headline(),
            'trends' => [
                'weeks' => $weekStarts->map(fn (Carbon $d) => $d->toDateString())->values(),
                'visits' => $this->weeklyCounts(Visit::query(), 'visit_date', $weekStarts),
                'revenue' => $this->weeklySums(Invoice::whereIn('status', self::BILLED_STATUSES), 'issue_date', 'total', $weekStarts),
                'incidents' => $this->weeklyCounts(Incident::query(), 'created_at', $weekStarts),
                'rota_coverage_pct' => $this->weeklyRotaCoverage($weekStarts),
            ],
            'risk' => $this->risk($request->user()->tenant_id),
            'branches' => $this->branchComparison(),
        ]);
    }

    /**
     * Monday-start dates for the last $n weeks, oldest first, including the
     * current (possibly partial) week.
     */
    protected function lastNWeekStarts(int $n): Collection
    {
        $thisWeekStart = now()->startOfWeek();

        return collect(range($n - 1, 0))->map(fn (int $i) => $thisWeekStart->copy()->subWeeks($i));
    }

    protected function headline(): array
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $today = now()->toDateString();

        $revenueThisMonth = Invoice::whereIn('status', self::BILLED_STATUSES)
            ->whereBetween('issue_date', [$monthStart, $today])
            ->sum('total');
        $outstanding = Invoice::whereIn('status', ['sent', 'overdue'])->sum('total');

        return [
            'active_service_users' => ServiceUser::where('status', 'active')->count(),
            'active_staff' => StaffProfile::where('employment_status', '!=', 'inactive')->count(),
            'visits_this_month' => Visit::whereBetween('visit_date', [$monthStart, $today])->count(),
            'revenue_this_month' => round((float) $revenueThisMonth, 2),
            'outstanding_invoices_total' => round((float) $outstanding, 2),
            'open_incidents' => Incident::where('status', '!=', 'closed')->count(),
        ];
    }

    protected function risk(?int $tenantId): array
    {
        $warningDays = (int) (Tenant::find($tenantId)?->setting('training_expiry_warning_days') ?? 30);

        return [
            'open_incidents' => Incident::where('status', '!=', 'closed')->count(),
            'non_compliant_requirements' => ComplianceRequirement::where('status', 'non_compliant')->count(),
            'training_expiring_soon' => TrainingRecord::whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>', now())
                ->whereDate('expiry_date', '<=', now()->addDays($warningDays))
                ->count(),
            'open_safeguarding_cases' => SafeguardingCase::where('status', '!=', 'closed')->count(),
        ];
    }

    protected function branchComparison(): array
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $today = now()->toDateString();

        return Branch::all()->map(fn (Branch $branch) => [
            'id' => $branch->id,
            'name' => $branch->name,
            'service_user_count' => ServiceUser::where('branch_id', $branch->id)->where('status', 'active')->count(),
            'staff_count' => StaffProfile::where('branch_id', $branch->id)->where('employment_status', '!=', 'inactive')->count(),
            'visits_this_month' => Visit::whereBetween('visit_date', [$monthStart, $today])
                ->whereHas('serviceUser', fn ($q) => $q->where('branch_id', $branch->id))
                ->count(),
            'open_incidents' => Incident::where('status', '!=', 'closed')
                ->whereHas('serviceUser', fn ($q) => $q->where('branch_id', $branch->id))
                ->count(),
        ])->values()->all();
    }

    /**
     * @return array<int, int>
     */
    protected function weeklyCounts($query, string $dateColumn, Collection $weekStarts): array
    {
        $rangeStart = $weekStarts->first();
        $counts = (clone $query)
            ->selectRaw("date_trunc('week', {$dateColumn}) as week_start, count(*) as total")
            ->where($dateColumn, '>=', $rangeStart)
            ->groupBy('week_start')
            ->pluck('total', 'week_start');

        return $this->alignToWeeks($counts, $weekStarts, fn ($v) => (int) $v);
    }

    /**
     * @return array<int, float>
     */
    protected function weeklySums($query, string $dateColumn, string $sumColumn, Collection $weekStarts): array
    {
        $rangeStart = $weekStarts->first();
        $sums = (clone $query)
            ->selectRaw("date_trunc('week', {$dateColumn}) as week_start, sum({$sumColumn}) as total")
            ->where($dateColumn, '>=', $rangeStart)
            ->groupBy('week_start')
            ->pluck('total', 'week_start');

        return $this->alignToWeeks($sums, $weekStarts, fn ($v) => round((float) $v, 2));
    }

    /**
     * @return array<int, float|null>
     */
    protected function weeklyRotaCoverage(Collection $weekStarts): array
    {
        $rangeStart = $weekStarts->first();

        $totals = Visit::selectRaw("date_trunc('week', visit_date) as week_start, count(*) as total")
            ->where('visit_date', '>=', $rangeStart)
            ->groupBy('week_start')
            ->pluck('total', 'week_start');

        $assigned = Visit::selectRaw("date_trunc('week', visit_date) as week_start, count(*) as total")
            ->where('visit_date', '>=', $rangeStart)
            ->whereNotNull('carer_id')
            ->groupBy('week_start')
            ->pluck('total', 'week_start');

        return $weekStarts->map(function (Carbon $week) use ($totals, $assigned) {
            $key = $this->matchWeekKey($totals->keys()->merge($assigned->keys()), $week);
            $total = $key !== null ? (int) ($totals[$key] ?? 0) : 0;
            $withCarer = $key !== null ? (int) ($assigned[$key] ?? 0) : 0;

            return $total > 0 ? round(($withCarer / $total) * 100, 1) : null;
        })->values()->all();
    }

    /**
     * Postgres returns date_trunc() keys as full timestamps (with driver-
     * dependent formatting), so align by calendar week rather than exact
     * string match.
     */
    protected function alignToWeeks(Collection $keyed, Collection $weekStarts, callable $cast): array
    {
        $keys = $keyed->keys();

        return $weekStarts->map(function (Carbon $week) use ($keyed, $keys, $cast) {
            $matchedKey = $this->matchWeekKey($keys, $week);

            return $matchedKey !== null ? $cast($keyed[$matchedKey]) : $cast(0);
        })->values()->all();
    }

    protected function matchWeekKey(Collection $keys, Carbon $week): ?string
    {
        foreach ($keys as $key) {
            if (Carbon::parse($key)->isSameWeek($week)) {
                return $key;
            }
        }

        return null;
    }
}
