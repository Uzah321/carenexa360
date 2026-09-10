<?php

namespace App\Modules\Payroll\Support;

use App\Models\User;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Rostering\Models\Shift;
use App\Modules\Staff\Models\StaffProfile;
use App\Modules\Visits\Models\Visit;
use App\Support\Scheduling\WorkedHours;
use Illuminate\Support\Collection;

class PayslipGenerator
{
    /**
     * Generate (or refresh) a payslip for every staff member with an hourly
     * rate set, for every completed visit/shift of theirs inside the period.
     * A payslip already `finalized` or `paid` is left untouched — re-running
     * generation for a period must never silently overwrite pay that's
     * already been signed off.
     *
     * @return Collection<int, Payslip>
     */
    public static function generateForPeriod(PayPeriod $payPeriod): Collection
    {
        $staffProfiles = StaffProfile::where('tenant_id', $payPeriod->tenant_id)
            ->whereNotNull('hourly_rate')
            ->get();

        return $staffProfiles->map(function (StaffProfile $staffProfile) use ($payPeriod) {
            $existing = Payslip::where('pay_period_id', $payPeriod->id)
                ->where('user_id', $staffProfile->user_id)
                ->first();

            if ($existing && in_array($existing->status, ['finalized', 'paid'], true)) {
                return $existing;
            }

            $hours = self::hoursForUser($staffProfile->user_id, $payPeriod);
            $grossPay = round($hours * (float) $staffProfile->hourly_rate, 2);
            $deductions = $existing->deductions ?? 0;

            $attributes = [
                'tenant_id' => $payPeriod->tenant_id,
                'regular_hours' => $hours,
                'gross_pay' => $grossPay,
                'deductions' => $deductions,
                'net_pay' => round($grossPay - $deductions, 2),
                'status' => 'draft',
                'generated_at' => now(),
            ];

            if ($existing) {
                $existing->update($attributes);

                return $existing->fresh();
            }

            return Payslip::create([
                ...$attributes,
                'pay_period_id' => $payPeriod->id,
                'user_id' => $staffProfile->user_id,
            ]);
        });
    }

    /**
     * Staff who never get a payslip on any pay period, silently, because
     * generateForPeriod()'s whereNotNull('hourly_rate') excludes them before
     * a payslip row is ever created — surfaced so a payroll admin sees *why*
     * the generated list is shorter than expected instead of just a shorter
     * list. Excludes inactive staff — someone who's left isn't missing pay,
     * they're just gone.
     *
     * @return Collection<int, array{id: int, name: string|null}>
     */
    public static function staffMissingHourlyRate(int $tenantId): Collection
    {
        return StaffProfile::where('tenant_id', $tenantId)
            ->where('employment_status', '!=', 'inactive')
            ->whereNull('hourly_rate')
            ->with('user')
            ->get()
            ->map(fn (StaffProfile $staffProfile) => [
                'id' => $staffProfile->user_id,
                'name' => $staffProfile->user?->name,
            ])
            ->values();
    }

    protected static function hoursForUser(int $userId, PayPeriod $payPeriod): float
    {
        $visitHours = Visit::where('carer_id', $userId)
            ->where('status', 'completed')
            ->whereBetween('visit_date', [$payPeriod->start_date, $payPeriod->end_date])
            ->get()
            ->sum(fn (Visit $visit) => WorkedHours::forVisit($visit));

        $shiftHours = Shift::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereBetween('shift_date', [$payPeriod->start_date, $payPeriod->end_date])
            ->get()
            ->sum(fn (Shift $shift) => WorkedHours::forShift($shift));

        return round($visitHours + $shiftHours, 2);
    }
}
