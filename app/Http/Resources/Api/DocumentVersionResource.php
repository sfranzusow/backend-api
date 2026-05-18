<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentVersionResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_id' => $this->document_id,
            'document_template_id' => $this->document_template_id,
            'version_number' => $this->version_number,
            'status' => $this->status,
            'title' => $this->title,
            'content_snapshot' => $this->content_snapshot,
            'template_snapshot' => $this->template_snapshot,
            'data_snapshot' => $this->data_snapshot,
            'metadata' => $this->metadata,
            'files' => DocumentFileResource::collection($this->whenLoaded('files')),
            'generated_by_id' => $this->generated_by_id,
            'generated_at' => $this->generated_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
