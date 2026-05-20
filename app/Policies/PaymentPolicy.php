<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Payment;
use App\Models\RentalAgreement;
use App\Models\User;

class PaymentPolicy
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

    public function view(User $authUser, Payment $payment): bool
    {
        $payable = $payment->payable;

        if ($payable instanceof RentalAgreement) {
            return $authUser->can('view', $payable);
        }

        return false;
    }

    public function createForRentalAgreement(User $authUser, RentalAgreement $rentalAgreement): bool
    {
        return $authUser->can('update', $rentalAgreement);
    }

    public function update(User $authUser, Payment $payment): bool
    {
        $payable = $payment->payable;

        if ($payable instanceof RentalAgreement) {
            return $authUser->can('update', $payable);
        }

        return false;
    }

    public function delete(User $authUser, Payment $payment): bool
    {
        return $this->update($authUser, $payment);
    }

    public function restore(User $authUser, Payment $payment): bool
    {
        return false;
    }

    public function forceDelete(User $authUser, Payment $payment): bool
    {
        return false;
    }
}
