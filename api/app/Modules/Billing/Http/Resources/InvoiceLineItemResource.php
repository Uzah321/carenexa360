<?php

namespace App\Modules\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceLineItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'visit_id' => $this->visit_id,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_rate' => $this->unit_rate,
            'amount' => $this->amount,
        ];
    }
}
