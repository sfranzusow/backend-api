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
            if ($authUser->hasRole(RoleName::Landlord->value)) {
                return $documentable->landlord_id === $authUser->id;
            }

            if ($authUser->hasRole(RoleName::Tenant->value)) {
                return $document->isVisibleToTenant()
                    && $documentable->tenant_id === $authUser->id;
            }
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

    public function share(User $authUser, Document $document): bool
    {
        return $this->update($authUser, $document);
    }

    public function voidDocument(User $authUser, Document $document): bool
    {
        return $this->update($authUser, $document);
    }

    public function download(User $authUser, Document $document): bool
    {
        return $this->view($authUser, $document);
    }

    public function uploadSigned(User $authUser, Document $document): bool
    {
        if ($authUser->hasRole(RoleName::Landlord->value)) {
            return $this->update($authUser, $document);
        }

        if ($authUser->hasRole(RoleName::Tenant->value)) {
            return $document->status === Document::STATUS_SHARED
                && $this->view($authUser, $document);
        }

        return $this->update($authUser, $document);
    }

    public function downloadSigned(User $authUser, Document $document): bool
    {
        return $this->view($authUser, $document);
    }

    public function delete(User $authUser, Document $document): bool
    {
        return $this->update($authUser, $document);
    }
}
