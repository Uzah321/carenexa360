<?php

namespace App\Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Http\Requests\UpdatePayslipRequest;
use App\Modules\Payroll\Http\Resources\PayslipResource;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Support\PayrollRoles;
use Illuminate\Http\Request;

class PayslipController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isPayrollAdmin = $user->hasAnyRole(PayrollRoles::ALLOWED);

        $payslips = Payslip::with(['payPeriod', 'user'])
            ->when(! $isPayrollAdmin, fn ($q) => $q->where('user_id', $user->id))
            ->when($isPayrollAdmin && $request->query('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->query('pay_period_id'), fn ($q, $id) => $q->where('pay_period_id', $id))
            ->orderByDesc('created_at')
            ->paginate(20);

        return PayslipResource::collection($payslips);
    }

    public function show(Request $request, Payslip $payslip)
    {
        $user = $request->user();
        abort_unless(
            $user->hasAnyRole(PayrollRoles::ALLOWED) || $user->id === $payslip->user_id,
            403
        );
        abort_unless($user->isPlatformAdmin() || $user->tenant_id === $payslip->tenant_id, 403);

        return new PayslipResource($payslip->load(['payPeriod', 'user']));
    }

    public function update(UpdatePayslipRequest $request, Payslip $payslip)
    {
        abort_unless($request->user()->hasAnyRole(PayrollRoles::ALLOWED), 403);
        abort_unless($request->user()->tenant_id === $payslip->tenant_id, 403);

        $attributes = $request->validated();

        if (array_key_exists('deductions', $attributes)) {
            $grossPay = (float) $payslip->gross_pay;
            $attributes['net_pay'] = round($grossPay - (float) $attributes['deductions'], 2);
        }

        $payslip->update($attributes);

        return new PayslipResource($payslip->fresh()->load(['payPeriod', 'user']));
    }
}
