<?php

namespace App\Http\Requests\Api;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->route('user');

        return $this->user()->can('update', $user);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'address_street' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_house_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'address_zip_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'address_city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_country' => ['sometimes', 'nullable', 'string', 'size:2'],
            'organization_id' => [
                Rule::prohibitedIf(fn () => ! $this->canManageOrganizationAssignment()),
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('organizations', 'id'),
            ],
            'current_password' => [
                Rule::excludeUnless(fn () => $this->user()->is($user) && $this->filled('password')),
                'required',
                'string',
                'current_password:sanctum',
            ],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ];
    }

    private function canManageOrganizationAssignment(): bool
    {
        $authUser = $this->user();

        if (! $authUser instanceof User) {
            return false;
        }

        return $authUser->hasRole(RoleName::Admin->value)
            || $authUser->can(PermissionName::UsersUpdate->value);
    }
}
