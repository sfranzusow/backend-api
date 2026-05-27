<?php

namespace App\Http\Requests\Api;

use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Organization::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('organizations', 'name')],
            'type' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }
}
