<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRentalAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:date_from'],
            'rent_cold' => ['sometimes', 'numeric', 'min:0'],
            'rent_warm' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'service_charges' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'deposit' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'terminated', 'ended'])],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
