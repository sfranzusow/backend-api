<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\DocumentLayoutTemplate;
use App\Models\Organization;
use App\Models\User;

class DocumentLayoutTemplatePolicy
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

    public function view(User $authUser, DocumentLayoutTemplate $documentLayoutTemplate): bool
    {
        return $this->ownsLayout($authUser, $documentLayoutTemplate);
    }

    public function create(User $authUser): bool
    {
        return $authUser->hasRole(RoleName::Landlord->value);
    }

    public function update(User $authUser, DocumentLayoutTemplate $documentLayoutTemplate): bool
    {
        return $this->ownsLayout($authUser, $documentLayoutTemplate);
    }

    public function activate(User $authUser, DocumentLayoutTemplate $documentLayoutTemplate): bool
    {
        return $this->update($authUser, $documentLayoutTemplate);
    }

    public function delete(User $authUser, DocumentLayoutTemplate $documentLayoutTemplate): bool
    {
        return $this->update($authUser, $documentLayoutTemplate);
    }

    public function restore(User $authUser, DocumentLayoutTemplate $documentLayoutTemplate): bool
    {
        return $this->update($authUser, $documentLayoutTemplate);
    }

    public function forceDelete(User $authUser, DocumentLayoutTemplate $documentLayoutTemplate): bool
    {
        return false;
    }

    private function ownsLayout(User $authUser, DocumentLayoutTemplate $documentLayoutTemplate): bool
    {
        if (! $authUser->hasRole(RoleName::Landlord->value)) {
            return false;
        }

        if ($documentLayoutTemplate->owner_type === Organization::class) {
            return $authUser->organization_id !== null
                && (int) $authUser->organization_id === (int) $documentLayoutTemplate->owner_id;
        }

        if ($documentLayoutTemplate->owner_type === User::class) {
            return (int) $authUser->id === (int) $documentLayoutTemplate->owner_id;
        }

        return false;
    }
}
