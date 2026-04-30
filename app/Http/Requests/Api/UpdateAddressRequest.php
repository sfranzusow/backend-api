<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAddressRequest extends FormRequest
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
            'street' => ['sometimes', 'string', 'max:255'],
            'house_number' => ['sometimes', 'string', 'max:20'],
            'zip_code' => ['sometimes', 'string', 'max:20'],
            'city' => ['sometimes', 'string', 'max:255'],
            'country' => ['sometimes', 'string', 'size:2'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
