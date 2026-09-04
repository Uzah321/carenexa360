<?php

namespace App\Modules\Documents\Models;

use App\Models\User;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Document extends Model
{
    use BelongsToTenant, HasAuditLog, HasFactory;

    protected $fillable = [
        'tenant_id',
        'documentable_type',
        'documentable_id',
        'category',
        'original_filename',
        'path',
        'mime_type',
        'size',
        'version',
        'uploaded_by',
        'expiry_date',
        'visible_to_family',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'visible_to_family' => 'boolean',
        ];
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
