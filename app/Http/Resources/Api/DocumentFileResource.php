<?php

namespace App\Http\Resources\Api;

use App\Enums\RoleName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentFileResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isTenantView = $this->isTenantView($request);

        return [
            'id' => $this->id,
            'document_version_id' => $this->document_version_id,
            'file_type' => $this->file_type,
            $this->mergeWhen(! $isTenantView, [
                'disk' => $this->disk,
                'path' => $this->path,
            ]),
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            $this->mergeWhen(! $isTenantView, [
                'checksum' => $this->checksum,
                'metadata' => $this->metadata,
                'uploaded_by_id' => $this->uploaded_by_id,
            ]),
            'uploaded_at' => $this->uploaded_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function isTenantView(Request $request): bool
    {
        $authUser = $request->user();

        return $authUser?->hasRole(RoleName::Tenant->value) === true
            && ! $authUser->hasRole(RoleName::Landlord->value)
            && ! $authUser->hasRole(RoleName::Admin->value);
    }
}
