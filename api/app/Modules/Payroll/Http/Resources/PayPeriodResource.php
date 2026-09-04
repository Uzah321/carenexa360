<?php

namespace App\Modules\Payroll\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayPeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'notes' => $this->notes,
            'payslips' => PayslipResource::collection($this->whenLoaded('payslips')),
            'created_at' => $this->created_at,
        ];
    }
}
