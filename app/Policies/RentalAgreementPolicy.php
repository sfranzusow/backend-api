<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\RentalAgreement;
use App\Models\User;

class RentalAgreementPolicy
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

    public function view(User $authUser, RentalAgreement $rentalAgreement): bool
    {
        if ($authUser->hasRole(RoleName::Landlord->value)) {
            return $rentalAgreement->landlord_id === $authUser->id;
        }

        if ($authUser->hasRole(RoleName::Tenant->value)) {
            return $rentalAgreement->tenant_id === $authUser->id;
        }

        return false;
    }

    public function create(User $authUser): bool
    {
        return $authUser->hasRole(RoleName::Landlord->value);
    }

    public function update(User $authUser, RentalAgreement $rentalAgreement): bool
    {
        return $this->isLandlordOfAgreement($authUser, $rentalAgreement);
    }

    public function delete(User $authUser, RentalAgreement $rentalAgreement): bool
    {
        return $this->isLandlordOfAgreement($authUser, $rentalAgreement);
    }

    private function isLandlordOfAgreement(User $authUser, RentalAgreement $rentalAgreement): bool
    {
        if (! $authUser->hasRole(RoleName::Landlord->value) || $rentalAgreement->landlord_id !== $authUser->id) {
            return false;
        }

        return $rentalAgreement->property()
            ->whereHas('users', function ($query) use ($authUser) {
                $query->where('users.id', $authUser->id)
                    ->where('property_user.role', RoleName::Landlord->value);
            })
            ->exists();
    }
}
