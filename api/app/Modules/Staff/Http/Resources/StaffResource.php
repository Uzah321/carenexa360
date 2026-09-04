<?php

namespace App\Modules\Staff\Http\Resources;

use App\Modules\Staff\Support\StaffRoles;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffResource extends JsonResource
{
    /**
     * name/roles/branch_id/skills stay visible to everyone — this resource
     * backs carer-assignment and witness-selection dropdowns used by every
     * staff role. HR-sensitive fields (pay, employment dates/status) are
     * only included for roles that manage staff records.
     */
    public function toArray(Request $request): array
    {
        $canManage = $request->user()?->hasAnyRole(StaffRoles::ALLOWED) ?? false;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->whenLoaded('user', fn () => $this->user?->name),
            'email' => $this->whenLoaded('user', fn () => $this->user?->email),
            'roles' => $this->whenLoaded('user', fn () => $this->user?->getRoleNames()),
            'branch_id' => $this->branch_id,
            'job_title' => $this->job_title,
            'skills' => $this->skills ?? [],
            // Used by the schedule page to filter inactive staff out of the
            // carer-assignment dropdown, so it stays visible to everyone.
            'employment_status' => $this->employment_status,
            'employee_number' => $this->when($canManage, $this->employee_number),
            'employment_start_date' => $this->when($canManage, fn () => $this->employment_start_date?->toDateString()),
            'hourly_rate' => $this->when($canManage, $this->hourly_rate),
            'created_at' => $this->created_at,
        ];
    }
}
