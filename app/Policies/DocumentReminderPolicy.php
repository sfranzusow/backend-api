<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentReminder;
use App\Models\User;

class DocumentReminderPolicy
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

    public function view(User $authUser, DocumentReminder $documentReminder): bool
    {
        return $authUser->can('view', $documentReminder->document);
    }

    public function createForDocument(User $authUser, Document $document): bool
    {
        return $authUser->can('update', $document);
    }

    public function update(User $authUser, DocumentReminder $documentReminder): bool
    {
        return $authUser->can('update', $documentReminder->document);
    }

    public function delete(User $authUser, DocumentReminder $documentReminder): bool
    {
        return $this->update($authUser, $documentReminder);
    }

    public function restore(User $authUser, DocumentReminder $documentReminder): bool
    {
        return false;
    }

    public function forceDelete(User $authUser, DocumentReminder $documentReminder): bool
    {
        return false;
    }
}
