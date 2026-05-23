<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\DocumentTemplate;
use App\Models\User;

class DocumentTemplatePolicy
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

    public function view(User $authUser, DocumentTemplate $documentTemplate): bool
    {
        return $authUser->hasRole(RoleName::Landlord->value)
            && $documentTemplate->status === DocumentTemplate::STATUS_ACTIVE;
    }

    public function create(User $authUser): bool
    {
        return false;
    }

    public function update(User $authUser, DocumentTemplate $documentTemplate): bool
    {
        return false;
    }

    public function activate(User $authUser, DocumentTemplate $documentTemplate): bool
    {
        return false;
    }

    public function delete(User $authUser, DocumentTemplate $documentTemplate): bool
    {
        return false;
    }

    public function restore(User $authUser, DocumentTemplate $documentTemplate): bool
    {
        return false;
    }

    public function forceDelete(User $authUser, DocumentTemplate $documentTemplate): bool
    {
        return false;
    }
}
