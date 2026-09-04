<?php

namespace App\Modules\Documents\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'version' => $this->version,
            'uploaded_by' => $this->uploaded_by,
            'uploaded_by_name' => $this->whenLoaded('uploadedBy', fn () => $this->uploadedBy?->name),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'visible_to_family' => $this->visible_to_family,
            'created_at' => $this->created_at,
        ];
    }
}
