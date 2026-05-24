<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\BankAccount;
use App\Models\User;

class BankAccountPolicy
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

    public function view(User $authUser, BankAccount $bankAccount): bool
    {
        return $authUser->hasRole(RoleName::Landlord->value)
            && $bankAccount->isVisibleTo($authUser);
    }

    public function create(User $authUser): bool
    {
        return $authUser->hasRole(RoleName::Landlord->value);
    }

    public function update(User $authUser, BankAccount $bankAccount): bool
    {
        return $this->view($authUser, $bankAccount);
    }

    public function delete(User $authUser, BankAccount $bankAccount): bool
    {
        return $this->update($authUser, $bankAccount);
    }

    public function restore(User $authUser, BankAccount $bankAccount): bool
    {
        return false;
    }

    public function forceDelete(User $authUser, BankAccount $bankAccount): bool
    {
        return false;
    }
}
