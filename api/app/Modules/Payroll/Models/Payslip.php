<?php

namespace App\Modules\Payroll\Models;

use App\Models\User;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    use BelongsToTenant, HasAuditLog, HasFactory;

    public const STATUSES = ['draft', 'finalized', 'paid'];

    protected $fillable = [
        'tenant_id',
        'pay_period_id',
        'user_id',
        'regular_hours',
        'gross_pay',
        'deductions',
        'net_pay',
        'status',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'regular_hours' => 'decimal:2',
            'gross_pay' => 'decimal:2',
            'deductions' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'generated_at' => 'datetime',
        ];
    }

    public function payPeriod(): BelongsTo
    {
        return $this->belongsTo(PayPeriod::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
