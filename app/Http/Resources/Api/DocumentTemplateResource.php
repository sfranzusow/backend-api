<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentTemplateResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'document_type' => $this->document_type,
            'template_type' => $this->template_type,
            'locale' => $this->locale,
            'version' => $this->version,
            'status' => $this->status,
            'content' => $this->content,
            'placeholders' => $this->placeholders,
            'metadata' => $this->metadata,
            'created_by_id' => $this->created_by_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
