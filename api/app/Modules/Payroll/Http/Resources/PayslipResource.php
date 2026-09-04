<?php

namespace App\Modules\Payroll\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayslipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pay_period_id' => $this->pay_period_id,
            'pay_period_start' => $this->whenLoaded('payPeriod', fn () => $this->payPeriod?->start_date?->toDateString()),
            'pay_period_end' => $this->whenLoaded('payPeriod', fn () => $this->payPeriod?->end_date?->toDateString()),
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user?->name),
            'regular_hours' => $this->regular_hours,
            'gross_pay' => $this->gross_pay,
            'deductions' => $this->deductions,
            'net_pay' => $this->net_pay,
            'status' => $this->status,
            'generated_at' => $this->generated_at,
        ];
    }
}
