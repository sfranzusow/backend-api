<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankAccountResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'organization_id' => $this->organization_id,
            'account_holder' => $this->account_holder,
            'iban' => $this->iban,
            'bic' => $this->bic,
            'bank_name' => $this->bank_name,
            'is_default' => $this->is_default,
            'user' => UserResource::make($this->whenLoaded('user')),
            'organization' => OrganizationResource::make($this->whenLoaded('organization')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
