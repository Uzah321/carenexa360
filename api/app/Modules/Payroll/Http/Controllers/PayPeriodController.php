<?php

namespace App\Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Http\Requests\StorePayPeriodRequest;
use App\Modules\Payroll\Http\Resources\PayPeriodResource;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Support\PayrollRoles;
use App\Modules\Payroll\Support\PayslipGenerator;
use Illuminate\Http\Request;

class PayPeriodController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(PayrollRoles::ALLOWED), 403);

        return PayPeriodResource::collection(
            PayPeriod::orderByDesc('start_date')->paginate(20)
        );
    }

    public function store(StorePayPeriodRequest $request)
    {
        abort_unless($request->user()->hasAnyRole(PayrollRoles::ALLOWED), 403);

        $payPeriod = PayPeriod::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
        ]);

        return (new PayPeriodResource($payPeriod))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, PayPeriod $payPeriod)
    {
        abort_unless($request->user()->hasAnyRole(PayrollRoles::ALLOWED), 403);
        abort_unless($request->user()->ownsTenant($payPeriod->tenant_id), 403);

        return new PayPeriodResource($payPeriod->load(['payslips.user']));
    }

    public function generatePayslips(Request $request, PayPeriod $payPeriod)
    {
        abort_unless($request->user()->hasAnyRole(PayrollRoles::ALLOWED), 403);
        abort_unless($request->user()->ownsTenant($payPeriod->tenant_id), 403);

        PayslipGenerator::generateForPeriod($payPeriod);

        return new PayPeriodResource($payPeriod->fresh()->load(['payslips.user']));
    }
}
