<?php

namespace App\Modules\Communication\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'title' => $this->title,
            'body' => $this->body,
            'posted_by' => $this->posted_by,
            'posted_by_name' => $this->whenLoaded('postedBy', fn () => $this->postedBy?->name),
            'pinned' => $this->pinned,
            'created_at' => $this->created_at,
        ];
    }
}
