<?php

namespace App\Services\Documents;

use App\Models\Address;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Organization;
use App\Models\RentalAgreement;
use App\Models\User;
use DateTimeInterface;

class RentalAgreementDocumentSnapshotFactory
{
    /**
     * @return array<string, mixed>
     */
    public function make(
        Document $document,
        DocumentTemplate $template,
        RentalAgreement $rentalAgreement,
        ?int $versionNumber = null,
        ?DateTimeInterface $generatedAt = null
    ): array {
        $property = $rentalAgreement->property;
        $address = $property?->address;
        $landlord = $rentalAgreement->landlord;

        return [
            'document' => [
                'id' => $document->id,
                'title' => $document->title ?? $template->name,
                'document_type' => $document->document_type,
                'version_number' => $versionNumber,
                'generated_at' => $generatedAt?->format(DATE_ATOM),
            ],
            'rental_agreement' => [
                'id' => $rentalAgreement->id,
                'date_from' => $rentalAgreement->date_from?->toDateString(),
                'date_to' => $rentalAgreement->date_to?->toDateString(),
                'rent_cold' => $rentalAgreement->rent_cold,
                'rent_warm' => $rentalAgreement->rent_warm,
                'service_charges' => $rentalAgreement->service_charges,
                'deposit' => $rentalAgreement->deposit,
                'currency' => $rentalAgreement->currency,
                'status' => $rentalAgreement->status,
                'lease_subject_description' => $rentalAgreement->lease_subject_description,
                'additional_spaces' => $rentalAgreement->additional_spaces,
                'shared_facilities' => $rentalAgreement->shared_facilities,
                'fixed_term_reason' => $rentalAgreement->fixed_term_reason,
                'handover_due_at' => $rentalAgreement->handover_due_at?->toDateString(),
                'operating_costs_allocation_key' => $rentalAgreement->operating_costs_allocation_key,
                'renovation_condition' => $rentalAgreement->renovation_condition,
                'renovation_condition_notes' => $rentalAgreement->renovation_condition_notes,
                'cosmetic_repairs_agreement' => $rentalAgreement->cosmetic_repairs_agreement,
                'small_repairs_single_limit' => $rentalAgreement->small_repairs_single_limit,
                'small_repairs_annual_limit' => $rentalAgreement->small_repairs_annual_limit,
                'handover_protocol_attached' => $rentalAgreement->handover_protocol_attached,
                'house_rules_attached' => $rentalAgreement->house_rules_attached,
                'operating_costs_overview_attached' => $rentalAgreement->operating_costs_overview_attached,
                'energy_certificate_attached' => $rentalAgreement->energy_certificate_attached,
                'self_disclosure_attached' => $rentalAgreement->self_disclosure_attached,
                'other_attachments' => $rentalAgreement->other_attachments,
                'individual_agreements' => $rentalAgreement->individual_agreements,
                'notes' => $rentalAgreement->notes,
            ],
            'property' => [
                'id' => $property?->id,
                'unit_number' => $property?->unit_number,
                'type' => $property?->type,
                'area_living' => $property?->area_living,
                'rooms' => $property?->rooms,
                'floor' => $property?->floor,
                'address' => $this->formatAddress($address),
                'address_details' => [
                    'street' => $address?->street,
                    'house_number' => $address?->house_number,
                    'zip_code' => $address?->zip_code,
                    'city' => $address?->city,
                    'country' => $address?->country,
                ],
            ],
            'landlord' => $this->userSnapshot($landlord),
            'tenant' => $this->userSnapshot($rentalAgreement->tenant),
            'organization' => $this->organizationSnapshot($landlord?->organization),
            'bank_account' => [
                'id' => $rentalAgreement->bankAccount?->id,
                'account_holder' => $rentalAgreement->bankAccount?->account_holder,
                'iban' => $rentalAgreement->bankAccount?->iban,
                'bic' => $rentalAgreement->bankAccount?->bic,
                'bank_name' => $rentalAgreement->bankAccount?->bank_name,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userSnapshot(?User $user): array
    {
        return [
            'id' => $user?->id,
            'name' => $user?->name,
            'email' => $user?->email,
            'phone_number' => $user?->phone_number,
            'address_street' => $user?->address_street,
            'address_house_number' => $user?->address_house_number,
            'address_zip_code' => $user?->address_zip_code,
            'address_city' => $user?->address_city,
            'address_country' => $user?->address_country,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function organizationSnapshot(?Organization $organization): array
    {
        return [
            'id' => $organization?->id,
            'name' => $organization?->name,
            'type' => $organization?->type,
            'email' => $organization?->email,
            'phone_number' => $organization?->phone_number,
            'website' => $organization?->website,
        ];
    }

    private function formatAddress(?Address $address): ?string
    {
        if ($address === null) {
            return null;
        }

        $street = trim(implode(' ', array_filter([
            $address->street,
            $address->house_number,
        ])));

        $city = trim(implode(' ', array_filter([
            $address->zip_code,
            $address->city,
        ])));

        return trim(implode(', ', array_filter([
            $street,
            $city,
            $address->country,
        ])));
    }
}
