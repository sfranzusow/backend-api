<?php

namespace App\Http\Requests\Api;

use App\Models\BankAccount;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateBankAccountRequest extends StoreBankAccountRequest
{
    public function authorize(): bool
    {
        $bankAccount = $this->route('bank_account');

        return $bankAccount instanceof BankAccount
            && ($this->user()?->can('update', $bankAccount) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->bankAccountRules(isUpdate: true);
    }
}
