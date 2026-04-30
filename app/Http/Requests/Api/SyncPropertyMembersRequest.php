<?php

namespace App\Http\Requests\Api;

use App\Models\Property;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncPropertyMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Property $property */
        $property = $this->route('property');

        return $this->user()->can('manageMembers', $property);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'members' => ['required', 'array'],
            'members.*.user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'members.*.role' => ['required', Rule::in(['landlord', 'tenant', 'manager'])],
            'members.*.start_date' => ['nullable', 'date'],
            'members.*.end_date' => ['nullable', 'date', 'after_or_equal:members.*.start_date'],
        ];
    }
}
