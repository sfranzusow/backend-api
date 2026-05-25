<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentTemplatePlaceholderResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $placeholder = $this->resource;

        return [
            'path' => $placeholder['path'],
            'label' => $placeholder['label'],
            'group' => $placeholder['group'],
            'type' => $placeholder['type'],
            'nullable' => $placeholder['nullable'],
            'example' => $placeholder['example'],
        ];
    }
}
