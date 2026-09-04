<?php

namespace App\Modules\Communication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Http\Requests\StoreAnnouncementRequest;
use App\Modules\Communication\Http\Resources\AnnouncementResource;
use App\Modules\Communication\Models\Announcement;
use App\Modules\Communication\Support\CommunicationRoles;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $branchId = $request->user()->staffProfile?->branch_id;

        $announcements = Announcement::with(['branch', 'postedBy'])
            ->where(fn ($q) => $q->whereNull('branch_id')->when($branchId, fn ($q2) => $q2->orWhere('branch_id', $branchId)))
            ->orderByDesc('pinned')
            ->orderByDesc('created_at')
            ->get();

        return AnnouncementResource::collection($announcements);
    }

    public function store(StoreAnnouncementRequest $request)
    {
        abort_unless($request->user()->hasAnyRole(CommunicationRoles::ALLOWED), 403);

        $announcement = Announcement::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
            'posted_by' => $request->user()->id,
        ]);

        return (new AnnouncementResource($announcement->load(['branch', 'postedBy'])))
            ->response()
            ->setStatusCode(201);
    }
}
