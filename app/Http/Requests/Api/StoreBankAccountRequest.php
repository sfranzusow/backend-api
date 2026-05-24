<?php

namespace App\Http\Requests\Api;

use App\Enums\RoleName;
use App\Models\BankAccount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', BankAccount::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->bankAccountRules();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function bankAccountRules(bool $isUpdate = false): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';

        return [
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'organization_id' => ['nullable', 'integer', Rule::exists('organizations', 'id')],
            'account_holder' => [$required, 'string', 'max:255'],
            'iban' => [$required, 'string', 'max:34', 'regex:/^[A-Z]{2}[0-9A-Z]{13,32}$/'],
            'bic' => ['nullable', 'string', 'max:11', 'regex:/^[A-Z0-9]{8}([A-Z0-9]{3})?$/'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateOwner($validator);
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function bankAccountAttributes(): array
    {
        $attributes = $this->validated();

        if (($attributes['user_id'] ?? null) !== null) {
            $attributes['organization_id'] = null;
        }

        if (($attributes['organization_id'] ?? null) !== null) {
            $attributes['user_id'] = null;
        }

        return $attributes;
    }

    protected function prepareForValidation(): void
    {
        $attributes = [];

        if ($this->has('iban') && is_string($this->input('iban'))) {
            $attributes['iban'] = strtoupper((string) preg_replace('/\s+/', '', $this->input('iban')));
        }

        if ($this->has('bic') && is_string($this->input('bic'))) {
            $attributes['bic'] = strtoupper((string) preg_replace('/\s+/', '', $this->input('bic')));
        }

        if ($attributes !== []) {
            $this->merge($attributes);
        }
    }

    protected function bankAccount(): ?BankAccount
    {
        $bankAccount = $this->route('bank_account');

        return $bankAccount instanceof BankAccount ? $bankAccount : null;
    }

    protected function validateOwner(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        [$userId, $organizationId] = $this->effectiveOwnerIds();

        if (($userId === null && $organizationId === null) || ($userId !== null && $organizationId !== null)) {
            $validator->errors()->add('user_id', 'Exactly one owner is required.');
            $validator->errors()->add('organization_id', 'Exactly one owner is required.');

            return;
        }

        $authUser = $this->user();

        if ($authUser === null || $authUser->hasRole(RoleName::Admin->value)) {
            return;
        }

        if ($userId === $authUser->id) {
            return;
        }

        if ($organizationId !== null && $organizationId === $authUser->organization_id) {
            return;
        }

        $validator->errors()->add('user_id', 'The bank account owner must be the authenticated user or their organization.');
        $validator->errors()->add('organization_id', 'The bank account owner must be the authenticated user or their organization.');
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function effectiveOwnerIds(): array
    {
        $bankAccount = $this->bankAccount();
        $userId = $this->has('user_id') ? $this->input('user_id') : $bankAccount?->user_id;
        $organizationId = $this->has('organization_id') ? $this->input('organization_id') : $bankAccount?->organization_id;

        if ($this->has('user_id') && $this->input('user_id') !== null && ! $this->has('organization_id')) {
            $organizationId = null;
        }

        if ($this->has('organization_id') && $this->input('organization_id') !== null && ! $this->has('user_id')) {
            $userId = null;
        }

        return [
            $userId === null ? null : (int) $userId,
            $organizationId === null ? null : (int) $organizationId,
        ];
    }
}
