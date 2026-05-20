<?php

namespace App\Services\Documents;

use App\Models\Address;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\RentalAgreement;
use App\Models\User;

class RentalAgreementDocumentSnapshotFactory
{
    /**
     * @return array<string, mixed>
     */
    public function make(Document $document, DocumentTemplate $template, RentalAgreement $rentalAgreement): array
    {
        $property = $rentalAgreement->property;
        $address = $property?->address;

        return [
            'document' => [
                'id' => $document->id,
                'title' => $document->title ?? $template->name,
                'document_type' => $document->document_type,
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
                'notes' => $rentalAgreement->notes,
            ],
            'property' => [
                'id' => $property?->id,
                'unit_number' => $property?->unit_number,
                'type' => $property?->type,
                'address' => $this->formatAddress($address),
                'address_details' => [
                    'street' => $address?->street,
                    'house_number' => $address?->house_number,
                    'zip_code' => $address?->zip_code,
                    'city' => $address?->city,
                    'country' => $address?->country,
                ],
            ],
            'landlord' => $this->userSnapshot($rentalAgreement->landlord),
            'tenant' => $this->userSnapshot($rentalAgreement->tenant),
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
