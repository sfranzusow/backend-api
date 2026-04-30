<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $address = $this->whenLoaded('address');
        $canViewAddress = $address !== null
            && $address !== false
            && $request->user()?->can('view', $address);

        return [
            'id' => $this->id,
            'address_id' => $this->address_id,
            'unit_number' => $this->unit_number,
            'type' => $this->type,
            'area_living' => $this->area_living,
            'rooms' => $this->rooms,
            'floor' => $this->floor,
            'build_year' => $this->build_year,
            'energy_class' => $this->energy_class,
            'price' => $this->price,
            'features' => $this->features,
            'status' => $this->status,
            'address' => $this->when($canViewAddress, fn () => AddressResource::make($address)),
            'members' => $this->whenLoaded('users', function () {
                return $this->users->map(fn ($user) => [
                    'user' => UserResource::make($user),
                    'role' => $user->pivot->role,
                    'start_date' => $user->pivot->start_date,
                    'end_date' => $user->pivot->end_date,
                ])->values();
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
