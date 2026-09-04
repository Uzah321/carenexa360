<?php

namespace App\Modules\Payroll\Http\Requests;

use App\Modules\Payroll\Models\Payslip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePayslipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user()->isPlatformAdmin();
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::in(Payslip::STATUSES)],
            'deductions' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
