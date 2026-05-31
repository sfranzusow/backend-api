<?php

namespace App\Http\Requests\Api;

use App\Enums\RoleName;
use App\Models\BankAccount;
use App\Models\Property;
use App\Models\RentalAgreement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRentalAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var RentalAgreement $rentalAgreement */
        $rentalAgreement = $this->route('rental_agreement');

        return $this->user()->can('update', $rentalAgreement);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'property_id' => ['sometimes', 'integer', Rule::exists('properties', 'id')],
            'landlord_id' => ['sometimes', 'integer', Rule::exists('users', 'id'), 'different:tenant_id'],
            'tenant_id' => ['sometimes', 'integer', Rule::exists('users', 'id')],
            'bank_account_id' => ['sometimes', 'nullable', 'integer', Rule::exists('bank_accounts', 'id')],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:date_from'],
            'rent_cold' => ['sometimes', 'numeric', 'min:0'],
            'rent_warm' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'service_charges' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'deposit' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'lease_subject_description' => ['sometimes', 'nullable', 'string'],
            'additional_spaces' => ['sometimes', 'nullable', 'string'],
            'shared_facilities' => ['sometimes', 'nullable', 'string'],
            'fixed_term_reason' => ['sometimes', 'nullable', 'string'],
            'handover_due_at' => ['sometimes', 'nullable', 'date'],
            'operating_costs_allocation_key' => ['sometimes', 'nullable', 'string'],
            'renovation_condition' => ['sometimes', 'nullable', 'string', Rule::in(RentalAgreement::renovationConditions())],
            'renovation_condition_notes' => ['sometimes', 'nullable', 'string'],
            'cosmetic_repairs_agreement' => ['sometimes', 'nullable', 'string'],
            'small_repairs_single_limit' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'small_repairs_annual_limit' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'handover_protocol_attached' => ['sometimes', 'boolean'],
            'house_rules_attached' => ['sometimes', 'boolean'],
            'operating_costs_overview_attached' => ['sometimes', 'boolean'],
            'energy_certificate_attached' => ['sometimes', 'boolean'],
            'self_disclosure_attached' => ['sometimes', 'boolean'],
            'other_attachments' => ['sometimes', 'nullable', 'string'],
            'individual_agreements' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::in(RentalAgreement::statuses())],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var RentalAgreement $rentalAgreement */
                $rentalAgreement = $this->route('rental_agreement');

                $this->validateStatusTransition($validator, $rentalAgreement);
                $this->validateAuthenticatedLandlordOwnership($validator);
                $this->validateBankAccountOwnership($validator, $rentalAgreement);
            },
        ];
    }

    private function validateStatusTransition(Validator $validator, RentalAgreement $rentalAgreement): void
    {
        $status = $this->input('status');

        if (! is_string($status) || $rentalAgreement->canTransitionToStatus($status)) {
            return;
        }

        $validator->errors()->add('status', 'The selected status transition is invalid.');
    }

    private function validateBankAccountOwnership(Validator $validator, RentalAgreement $rentalAgreement): void
    {
        if (! $this->has('bank_account_id') && ! $this->has('landlord_id')) {
            return;
        }

        $bankAccountId = $this->has('bank_account_id')
            ? $this->input('bank_account_id')
            : $rentalAgreement->bank_account_id;

        if ($bankAccountId === null) {
            return;
        }

        $landlordId = $this->has('landlord_id')
            ? $this->integer('landlord_id')
            : $rentalAgreement->landlord_id;

        if (! BankAccount::existsForLandlord((int) $bankAccountId, $landlordId)) {
            $validator->errors()->add('bank_account_id', 'The bank account must belong to the selected landlord or their organization.');
        }
    }

    private function validateAuthenticatedLandlordOwnership(Validator $validator): void
    {
        $authUser = $this->user();

        if ($authUser === null || $authUser->hasRole(RoleName::Admin->value)) {
            return;
        }

        if ($this->has('landlord_id') && $this->integer('landlord_id') !== $authUser->id) {
            $validator->errors()->add('landlord_id', 'The landlord must be the authenticated user.');
        }

        if ($this->has('property_id') && ! $this->authenticatedLandlordManagesProperty($this->integer('property_id'))) {
            $validator->errors()->add('property_id', 'The property must be managed by the authenticated landlord.');
        }
    }

    private function authenticatedLandlordManagesProperty(int $propertyId): bool
    {
        return Property::query()
            ->whereKey($propertyId)
            ->whereHas('users', function ($query) {
                $query->where('users.id', $this->user()?->id)
                    ->where('property_user.role', RoleName::Landlord->value);
            })
            ->exists();
    }
}
