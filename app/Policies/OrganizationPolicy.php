<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
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
        return false;
    }

    public function view(User $authUser, Organization $organization): bool
    {
        return false;
    }

    public function create(User $authUser): bool
    {
        return false;
    }

    public function update(User $authUser, Organization $organization): bool
    {
        return false;
    }

    public function delete(User $authUser, Organization $organization): bool
    {
        return false;
    }

    public function restore(User $authUser, Organization $organization): bool
    {
        return false;
    }

    public function forceDelete(User $authUser, Organization $organization): bool
    {
        return false;
    }
}
