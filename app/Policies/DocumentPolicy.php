<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Document;
use App\Models\RentalAgreement;
use App\Models\User;

class DocumentPolicy
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

    public function view(User $authUser, Document $document): bool
    {
        $documentable = $document->documentable;

        if ($documentable instanceof RentalAgreement) {
            return $authUser->can('view', $documentable);
        }

        return false;
    }

    public function createForRentalAgreement(User $authUser, RentalAgreement $rentalAgreement): bool
    {
        return $authUser->can('update', $rentalAgreement);
    }

    public function update(User $authUser, Document $document): bool
    {
        $documentable = $document->documentable;

        if ($documentable instanceof RentalAgreement) {
            return $authUser->can('update', $documentable);
        }

        return false;
    }

    public function generate(User $authUser, Document $document): bool
    {
        return $this->update($authUser, $document);
    }

    public function download(User $authUser, Document $document): bool
    {
        return $this->view($authUser, $document);
    }

    public function delete(User $authUser, Document $document): bool
    {
        return $this->update($authUser, $document);
    }
}
