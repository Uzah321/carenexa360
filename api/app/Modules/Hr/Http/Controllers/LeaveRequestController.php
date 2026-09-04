<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Http\Requests\StoreLeaveRequestRequest;
use App\Modules\Hr\Http\Requests\UpdateLeaveRequestRequest;
use App\Modules\Hr\Http\Resources\LeaveRequestResource;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Support\HrRoles;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isHrAdmin = $user->hasAnyRole(HrRoles::ALLOWED);

        $leaveRequests = LeaveRequest::with(['user', 'approvedBy'])
            ->when(! $isHrAdmin, fn ($q) => $q->where('user_id', $user->id))
            ->when($isHrAdmin && $request->query('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(20);

        return LeaveRequestResource::collection($leaveRequests);
    }

    public function store(StoreLeaveRequestRequest $request)
    {
        $leaveRequest = LeaveRequest::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
            'user_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        return (new LeaveRequestResource($leaveRequest->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateLeaveRequestRequest $request, LeaveRequest $leaveRequest)
    {
        $user = $request->user();
        abort_unless($user->tenant_id === $leaveRequest->tenant_id, 403);

        $status = $request->validated('status');
        $isHrAdmin = $user->hasAnyRole(HrRoles::ALLOWED);

        if (in_array($status, ['approved', 'rejected'], true)) {
            abort_unless($isHrAdmin, 403);

            $leaveRequest->update([
                'status' => $status,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'notes' => $request->validated('notes', $leaveRequest->notes),
            ]);

            return new LeaveRequestResource($leaveRequest->fresh()->load(['user', 'approvedBy']));
        }

        if ($status === 'cancelled') {
            abort_unless($isHrAdmin || $user->id === $leaveRequest->user_id, 403);

            if ($leaveRequest->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => ['Only a pending request can be cancelled.'],
                ]);
            }

            $leaveRequest->update(['status' => 'cancelled']);

            return new LeaveRequestResource($leaveRequest->fresh()->load(['user', 'approvedBy']));
        }

        abort(422, 'Unsupported status transition.');
    }
}
