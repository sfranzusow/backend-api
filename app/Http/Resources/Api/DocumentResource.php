<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'documentable_type' => class_basename($this->documentable_type),
            'documentable_id' => $this->documentable_id,
            'document_template_id' => $this->document_template_id,
            'document_type' => $this->document_type,
            'status' => $this->status,
            'title' => $this->title,
            'metadata' => $this->metadata,
            'template' => DocumentTemplateResource::make($this->whenLoaded('template')),
            'latest_version' => DocumentVersionResource::make($this->whenLoaded('latestVersion')),
            'created_by_id' => $this->created_by_id,
            'creator' => UserResource::make($this->whenLoaded('creator')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
