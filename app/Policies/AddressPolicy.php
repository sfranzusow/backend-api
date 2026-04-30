<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Address;
use App\Models\User;

class AddressPolicy
{
    public function before(User $authUser, string $ability): ?bool
    {
        if ($authUser->hasRole(RoleName::Admin->value)) {
            return true;
        }

        return null;
    }

    public function viewAny(User $authUser): bool
    {
        return $authUser->hasRole(RoleName::Landlord->value);
    }

    public function view(User $authUser, Address $address): bool
    {
        return $this->isLandlordOfAddress($authUser, $address);
    }

    public function create(User $authUser): bool
    {
        return $authUser->hasRole(RoleName::Landlord->value);
    }

    public function update(User $authUser, Address $address): bool
    {
        return $this->isLandlordOfAddress($authUser, $address);
    }

    public function delete(User $authUser, Address $address): bool
    {
        return $this->isLandlordOfAddress($authUser, $address);
    }

    private function isLandlordOfAddress(User $authUser, Address $address): bool
    {
        if (! $authUser->hasRole(RoleName::Landlord->value)) {
            return false;
        }

        return $address->properties()
            ->whereHas('users', function ($query) use ($authUser) {
                $query
                    ->where('users.id', $authUser->id)
                    ->where('property_user.role', RoleName::Landlord->value);
            })
            ->exists();
    }
}
