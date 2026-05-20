<?php

namespace App\Http\Resources\Api;

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
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
