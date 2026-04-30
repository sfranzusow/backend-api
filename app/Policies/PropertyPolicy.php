<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Property;
use App\Models\User;

class PropertyPolicy
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
        return $authUser->hasAnyRole([
            RoleName::Landlord->value,
            RoleName::Tenant->value,
        ]);
    }

    public function view(User $authUser, Property $property): bool
    {
        if ($authUser->hasRole(RoleName::Landlord->value)) {
            return $property->users()
                ->where('users.id', $authUser->id)
                ->where('property_user.role', RoleName::Landlord->value)
                ->exists();
        }

        if ($authUser->hasRole(RoleName::Tenant->value)) {
            return $property->users()
                ->where('users.id', $authUser->id)
                ->where('property_user.role', RoleName::Tenant->value)
                ->exists();
        }

        return false;
    }

    public function create(User $authUser): bool
    {
        return $authUser->hasRole(RoleName::Landlord->value);
    }

    public function update(User $authUser, Property $property): bool
    {
        return $this->isLandlordOfProperty($authUser, $property);
    }

    public function delete(User $authUser, Property $property): bool
    {
        return $this->isLandlordOfProperty($authUser, $property);
    }

    public function manageMembers(User $authUser, Property $property): bool
    {
        return $this->isLandlordOfProperty($authUser, $property);
    }

    private function isLandlordOfProperty(User $authUser, Property $property): bool
    {
        if (! $authUser->hasRole(RoleName::Landlord->value)) {
            return false;
        }

        return $property->users()
            ->where('users.id', $authUser->id)
            ->where('property_user.role', RoleName::Landlord->value)
            ->exists();
    }
}
