<?php

namespace App\Http\Requests\Api;

use App\Models\Property;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Property::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'address_id' => ['required', 'integer', Rule::exists('addresses', 'id')],
            'unit_number' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(['apartment', 'office', 'penthouse', 'studio'])],
            'area_living' => ['required', 'numeric', 'min:0'],
            'rooms' => ['required', 'integer', 'min:0'],
            'floor' => ['nullable', 'integer'],
            'build_year' => ['nullable', 'integer', 'between:1800,'.(date('Y') + 1)],
            'energy_class' => ['nullable', 'string', 'max:3'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'features' => ['nullable', 'array'],
            'status' => ['sometimes', Rule::in(['available', 'rented', 'sold'])],
        ];
    }
}
