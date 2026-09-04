<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Http\Requests\GenerateInvoiceRequest;
use App\Modules\Billing\Http\Requests\UpdateInvoiceRequest;
use App\Modules\Billing\Http\Resources\InvoiceResource;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Support\FinanceRoles;
use App\Modules\Organization\Models\Tenant;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\Visits\Models\Visit;
use App\Support\Scheduling\WorkedHours;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(FinanceRoles::ALLOWED), 403);

        $invoices = Invoice::with(['serviceUser', 'funder'])
            ->when($request->query('service_user_id'), fn ($q, $id) => $q->where('service_user_id', $id))
            ->when($request->query('funder_id'), fn ($q, $id) => $q->where('funder_id', $id))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(20);

        return InvoiceResource::collection($invoices);
    }

    public function generate(GenerateInvoiceRequest $request)
    {
        abort_unless($request->user()->hasAnyRole(FinanceRoles::ALLOWED), 403);

        $serviceUser = ServiceUser::findOrFail($request->validated('service_user_id'));
        abort_unless($request->user()->tenant_id === $serviceUser->tenant_id, 403);

        $rate = (float) $request->validated('hourly_rate');

        $visits = Visit::where('service_user_id', $serviceUser->id)
            ->where('status', 'completed')
            ->whereBetween('visit_date', [$request->validated('period_start'), $request->validated('period_end')])
            ->orderBy('visit_date')
            ->get();

        $invoice = DB::transaction(function () use ($request, $serviceUser, $visits, $rate) {
            $invoice = Invoice::create([
                'tenant_id' => $serviceUser->tenant_id,
                'service_user_id' => $serviceUser->id,
                'funder_id' => $request->validated('funder_id'),
                'period_start' => $request->validated('period_start'),
                'period_end' => $request->validated('period_end'),
                'issue_date' => now()->toDateString(),
                'due_date' => $request->validated('due_date'),
                'status' => 'draft',
                'currency' => Tenant::find($serviceUser->tenant_id)?->currency ?? 'USD',
                'notes' => $request->validated('notes'),
                'created_by' => $request->user()->id,
            ]);

            $subtotal = 0;

            foreach ($visits as $visit) {
                $hours = WorkedHours::forVisit($visit);
                $amount = round($hours * $rate, 2);
                $subtotal += $amount;

                $invoice->lineItems()->create([
                    'tenant_id' => $invoice->tenant_id,
                    'visit_id' => $visit->id,
                    'description' => "Visit on {$visit->visit_date->toDateString()} ({$visit->start_time}–{$visit->end_time})",
                    'quantity' => $hours,
                    'unit_rate' => $rate,
                    'amount' => $amount,
                ]);
            }

            $invoice->update([
                'invoice_number' => "INV-{$invoice->id}",
                'subtotal' => $subtotal,
                'total' => round($subtotal + (float) $invoice->tax_amount, 2),
            ]);

            return $invoice;
        });

        return (new InvoiceResource($invoice->fresh()->load(['serviceUser', 'funder', 'lineItems'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Invoice $invoice)
    {
        abort_unless($request->user()->hasAnyRole(FinanceRoles::ALLOWED), 403);
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $invoice->tenant_id,
            403
        );

        return new InvoiceResource($invoice->load(['serviceUser', 'funder', 'lineItems']));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice)
    {
        abort_unless($request->user()->hasAnyRole(FinanceRoles::ALLOWED), 403);
        abort_unless($request->user()->tenant_id === $invoice->tenant_id, 403);

        $invoice->update($request->validated());

        return new InvoiceResource($invoice->fresh()->load(['serviceUser', 'funder', 'lineItems']));
    }
}
