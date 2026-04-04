<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;

class UserPolicy
{
    public function before(User $authUser, string $ability): ?bool
    {
        if (
            $authUser->hasRole(RoleName::Admin->value)
            && ! in_array($ability, ['assignRoles', 'delete'], true)
        ) {
            return true;
        }

        return null;
    }

    public function viewAny(User $authUser): bool
    {
        return $authUser->can(PermissionName::UsersViewAny->value);
    }

    public function create(User $authUser): bool
    {
        return $authUser->can(PermissionName::UsersCreate->value);
    }

    public function view(User $authUser, User $user): bool
    {
        if ($authUser->id === $user->id) {
            return $authUser->can(PermissionName::ProfileViewOwn->value);
        }

        return $authUser->can(PermissionName::UsersView->value);
    }

    public function update(User $authUser, User $user): bool
    {
        if ($authUser->id === $user->id) {
            return $authUser->can(PermissionName::ProfileUpdateOwn->value);
        }

        return $authUser->can(PermissionName::UsersUpdate->value);
    }

    public function assignRoles(User $authUser, User $user): bool
    {
        if ($authUser->id === $user->id) {
            return false;
        }

        if ($authUser->hasRole(RoleName::Admin->value)) {
            return true;
        }

        return $authUser->can(PermissionName::UsersAssignRoles->value);
    }

    public function delete(User $authUser, User $user): bool
    {
        if ($authUser->id === $user->id) {
            return false;
        }

        if ($authUser->hasRole(RoleName::Admin->value)) {
            return true;
        }

        return $authUser->can(PermissionName::UsersDelete->value);
    }
}
