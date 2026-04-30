<?php

namespace App\Http\Requests\Api;

use App\Models\Property;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Property $property */
        $property = $this->route('property');

        return $this->user()->can('update', $property);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'address_id' => ['sometimes', 'integer', Rule::exists('addresses', 'id')],
            'unit_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(['apartment', 'office', 'penthouse', 'studio'])],
            'area_living' => ['sometimes', 'numeric', 'min:0'],
            'rooms' => ['sometimes', 'integer', 'min:0'],
            'floor' => ['sometimes', 'nullable', 'integer'],
            'build_year' => ['sometimes', 'nullable', 'integer', 'between:1800,'.(date('Y') + 1)],
            'energy_class' => ['sometimes', 'nullable', 'string', 'max:3'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'features' => ['sometimes', 'nullable', 'array'],
            'status' => ['sometimes', Rule::in(['available', 'rented', 'sold'])],
        ];
    }
}
