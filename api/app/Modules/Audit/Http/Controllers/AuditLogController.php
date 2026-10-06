<?php

namespace App\Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Http\Resources\AuditLogResource;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Audit\Support\AuditDescriber;
use App\Modules\Audit\Support\AuditRoles;
use App\Support\Time\TenantClock;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(AuditRoles::ALLOWED), 403);

        $filters = $request->validate([
            'action' => ['nullable', Rule::in(['created', 'updated', 'deleted'])],
            'record_type' => ['nullable', 'string', Rule::in(array_column(AuditDescriber::recordTypes(), 'value'))],
            'record_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $tenantId = $request->user()->tenant_id;

        return AuditLogResource::collection(
            AuditLog::with('user')
                ->when($filters['action'] ?? null, fn ($q, $action) => $q->where('action', $action))
                ->when($filters['record_type'] ?? null, fn ($q, $type) => $q->where('auditable_type', 'like', '%\\'.$type))
                ->when($filters['record_id'] ?? null, fn ($q, $id) => $q->where('auditable_id', $id))
                ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
                // Dates are the organisation's local days.
                ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', TenantClock::dayBoundsUtc($tenantId, $from)[0]))
                ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', TenantClock::dayBoundsUtc($tenantId, $to)[1]))
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString()
        );
    }

    /** One entry in full: who, when, from where, what it was done to, and every field that changed. */
    public function show(Request $request, AuditLog $auditLog)
    {
        abort_unless($request->user()->hasAnyRole(AuditRoles::ALLOWED), 403);
        abort_unless($request->user()->isPlatformAdmin() || $request->user()->tenant_id === $auditLog->tenant_id, 403);

        return (new AuditLogResource($auditLog->load('user.roles')))->withDetail();
    }

    /** The kinds of record the log covers, for the filter. */
    public function recordTypes(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(AuditRoles::ALLOWED), 403);

        return response()->json(['data' => AuditDescriber::recordTypes()]);
    }
}
