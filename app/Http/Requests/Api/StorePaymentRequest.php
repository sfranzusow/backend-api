<?php

namespace App\Http\Requests\Api;

use App\Models\Payment;
use App\Models\RentalAgreement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var RentalAgreement $rentalAgreement */
        $rentalAgreement = $this->route('rental_agreement');

        return $this->user()?->can('createForRentalAgreement', [Payment::class, $rentalAgreement]) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(Payment::types())],
            'direction' => ['required', 'string', Rule::in(Payment::directions())],
            'status' => ['sometimes', 'string', Rule::in(Payment::statuses())],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'due_date' => ['nullable', 'date'],
            'paid_at' => ['nullable', 'date'],
            'payer_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'payee_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'description' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('currency') && is_string($this->input('currency'))) {
            $this->merge([
                'currency' => Str::upper($this->string('currency')->toString()),
            ]);
        }
    }
}
