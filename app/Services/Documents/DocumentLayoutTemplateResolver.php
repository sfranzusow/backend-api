<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\DocumentLayoutTemplate;
use App\Models\DocumentTemplate;
use App\Models\Organization;
use App\Models\RentalAgreement;
use App\Models\User;

class DocumentLayoutTemplateResolver
{
    public function activeFor(
        Document $document,
        DocumentTemplate $template,
        RentalAgreement $rentalAgreement
    ): ?DocumentLayoutTemplate {
        $landlord = $rentalAgreement->landlord;

        if ($landlord?->organization_id !== null) {
            $layout = $this->activeForOwner(
                Organization::class,
                (int) $landlord->organization_id,
                $document->document_type,
                $template->locale
            );

            if ($layout instanceof DocumentLayoutTemplate) {
                return $layout;
            }
        }

        if ($landlord instanceof User) {
            return $this->activeForOwner(
                User::class,
                (int) $landlord->id,
                $document->document_type,
                $template->locale
            );
        }

        return null;
    }

    private function activeForOwner(
        string $ownerType,
        int $ownerId,
        string $documentType,
        string $locale
    ): ?DocumentLayoutTemplate {
        return DocumentLayoutTemplate::query()
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->where('document_type', $documentType)
            ->where('locale', $locale)
            ->where('status', DocumentLayoutTemplate::STATUS_ACTIVE)
            ->latest('version')
            ->latest('id')
            ->first();
    }
}
