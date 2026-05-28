<?php

namespace App\Http\Resources\Api;

use App\Models\Payment as PaymentModel;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payable_type' => class_basename($this->payable_type),
            'payable_id' => $this->payable_id,
            'type' => $this->type,
            'direction' => $this->direction,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'due_date' => $this->due_date?->toDateString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'payer_id' => $this->payer_id,
            'payer' => UserResource::make($this->whenLoaded('payer')),
            'payee_id' => $this->payee_id,
            'payee' => UserResource::make($this->whenLoaded('payee')),
            'description' => $this->description,
            'metadata' => $this->metadata,
            'reminders' => ReminderResource::collection($this->whenLoaded('reminders')),
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
        $payment = $this->resource;

        if (! $authUser instanceof User || ! $payment instanceof PaymentModel) {
            return [
                'update' => false,
                'delete' => false,
                'create_reminder' => false,
            ];
        }

        return [
            'update' => $authUser->can('update', $payment),
            'delete' => $authUser->can('delete', $payment),
            'create_reminder' => $authUser->can('createForRemindable', [Reminder::class, $payment]),
        ];
    }
}
