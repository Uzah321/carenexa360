<?php

namespace App\Modules\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_user_id' => $this->service_user_id,
            'service_user_name' => $this->whenLoaded(
                'serviceUser',
                fn () => $this->serviceUser ? trim("{$this->serviceUser->first_name} {$this->serviceUser->last_name}") : null
            ),
            'funder_id' => $this->funder_id,
            'funder_name' => $this->whenLoaded('funder', fn () => $this->funder?->name),
            'invoice_number' => $this->invoice_number,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'issue_date' => $this->issue_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'line_items' => InvoiceLineItemResource::collection($this->whenLoaded('lineItems')),
            'created_at' => $this->created_at,
        ];
    }
}
