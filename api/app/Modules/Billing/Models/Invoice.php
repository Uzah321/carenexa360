<?php

namespace App\Modules\Billing\Models;

use App\Models\User;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToTenant, HasAuditLog, HasFactory;

    public const STATUSES = ['draft', 'sent', 'paid', 'overdue', 'cancelled'];

    protected $fillable = [
        'tenant_id',
        'service_user_id',
        'funder_id',
        'invoice_number',
        'period_start',
        'period_end',
        'issue_date',
        'due_date',
        'status',
        'subtotal',
        'tax_amount',
        'total',
        'currency',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function serviceUser(): BelongsTo
    {
        return $this->belongsTo(ServiceUser::class);
    }

    public function funder(): BelongsTo
    {
        return $this->belongsTo(Funder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }
}
