<?php

namespace App\Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Http\Resources\AuditLogResource;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Audit\Support\AuditRoles;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(AuditRoles::ALLOWED), 403);

        return AuditLogResource::collection(
            AuditLog::with('user')
                ->orderByDesc('created_at')
                ->paginate(20)
        );
    }
}
