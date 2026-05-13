<?php

namespace App\Http\Requests\Api;

use App\Enums\RoleName;
use App\Models\Property;
use App\Models\RentalAgreement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRentalAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', RentalAgreement::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'property_id' => ['required', 'integer', Rule::exists('properties', 'id')],
            'landlord_id' => ['required', 'integer', Rule::exists('users', 'id'), 'different:tenant_id'],
            'tenant_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'date_from' => ['required', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'rent_cold' => ['required', 'numeric', 'min:0'],
            'rent_warm' => ['nullable', 'numeric', 'min:0'],
            'service_charges' => ['nullable', 'numeric', 'min:0'],
            'deposit' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'status' => ['sometimes', Rule::in([RentalAgreement::STATUS_DRAFT])],
            'notes' => ['nullable', 'string'],
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

                $authUser = $this->user();

                if ($authUser === null || $authUser->hasRole(RoleName::Admin->value)) {
                    return;
                }

                if ($this->integer('landlord_id') !== $authUser->id) {
                    $validator->errors()->add('landlord_id', 'The landlord must be the authenticated user.');
                }

                if (! $this->authenticatedLandlordManagesProperty($this->integer('property_id'))) {
                    $validator->errors()->add('property_id', 'The property must be managed by the authenticated landlord.');
                }
            },
        ];
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
