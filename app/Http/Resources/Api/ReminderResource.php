<?php

namespace App\Http\Resources\Api;

use App\Models\Reminder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReminderResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'remindable_type' => class_basename($this->remindable_type),
            'remindable_id' => $this->remindable_id,
            'title' => $this->title,
            'notes' => $this->notes,
            'due_at' => $this->due_at?->toISOString(),
            'remind_at' => $this->remind_at?->toISOString(),
            'status' => $this->status,
            'display_status' => $this->displayStatus(),
            'metadata' => $this->metadata,
            'assigned_to_id' => $this->assigned_to_id,
            'assignee' => UserResource::make($this->whenLoaded('assignee')),
            'created_by_id' => $this->created_by_id,
            'creator' => UserResource::make($this->whenLoaded('creator')),
            'completed_at' => $this->completed_at?->toISOString(),
            'actions' => $this->actions($request),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function actions(Request $request): array
    {
        $authUser = $request->user();
        $reminder = $this->resource;

        if (! $authUser instanceof User || ! $reminder instanceof Reminder) {
            return $this->emptyActions();
        }

        $canUpdate = $authUser->can('update', $reminder);

        return [
            'update' => $canUpdate,
            'delete' => $authUser->can('delete', $reminder),
            'mark_done' => $canUpdate && $reminder->status === Reminder::STATUS_PENDING,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function emptyActions(): array
    {
        return [
            'update' => false,
            'delete' => false,
            'mark_done' => false,
        ];
    }
}
