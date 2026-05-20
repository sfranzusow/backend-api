<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentReminderResource extends JsonResource
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
            'title' => $this->title,
            'notes' => $this->notes,
            'due_at' => $this->due_at?->toISOString(),
            'remind_at' => $this->remind_at?->toISOString(),
            'status' => $this->status,
            'metadata' => $this->metadata,
            'assigned_to_id' => $this->assigned_to_id,
            'assignee' => UserResource::make($this->whenLoaded('assignee')),
            'created_by_id' => $this->created_by_id,
            'creator' => UserResource::make($this->whenLoaded('creator')),
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
