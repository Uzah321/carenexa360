<?php

namespace App\Modules\Billing\Models;

use App\Modules\Visits\Models\Visit;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLineItem extends Model
{
    use BelongsToTenant, HasAuditLog, HasFactory;

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'visit_id',
        'description',
        'quantity',
        'unit_rate',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_rate' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}
