<?php

namespace App\Http\Resources\Api;

use App\Models\DocumentLayoutTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentLayoutTemplateResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DocumentLayoutTemplate $layout */
        $layout = $this->resource;

        return [
            'id' => $this->id,
            'owner_type' => DocumentLayoutTemplate::ownerTypeLabel($layout->owner_type),
            'owner_id' => $this->owner_id,
            'name' => $this->name,
            'document_type' => $this->document_type,
            'locale' => $this->locale,
            'version' => $this->version,
            'status' => $this->status,
            'header_enabled' => $this->header_enabled,
            'footer_enabled' => $this->footer_enabled,
            'page_numbers_enabled' => $this->page_numbers_enabled,
            'header_content' => $this->header_content,
            'footer_content' => $this->footer_content,
            'header_banner_path' => $this->header_banner_path,
            'footer_banner_path' => $this->footer_banner_path,
            'placeholders' => $this->placeholders,
            'metadata' => $this->metadata,
            'created_by_id' => $this->created_by_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
