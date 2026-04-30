<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RentalAgreementResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            'landlord_id' => $this->landlord_id,
            'tenant_id' => $this->tenant_id,
            'date_from' => $this->date_from?->toDateString(),
            'date_to' => $this->date_to?->toDateString(),
            'rent_cold' => $this->rent_cold,
            'rent_warm' => $this->rent_warm,
            'service_charges' => $this->service_charges,
            'deposit' => $this->deposit,
            'currency' => $this->currency,
            'status' => $this->status,
            'notes' => $this->notes,
            'property' => PropertyResource::make($this->whenLoaded('property')),
            'landlord' => UserResource::make($this->whenLoaded('landlord')),
            'tenant' => UserResource::make($this->whenLoaded('tenant')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
