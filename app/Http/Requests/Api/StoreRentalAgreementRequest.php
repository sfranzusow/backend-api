<?php

namespace App\Http\Requests\Api;

use App\Models\RentalAgreement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'status' => ['sometimes', Rule::in(['draft', 'active', 'terminated', 'ended'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
