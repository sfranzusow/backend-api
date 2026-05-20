<?php

namespace App\Http\Requests\Api;

use App\Models\Payment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Payment $payment */
        $payment = $this->route('payment');

        return $this->user()?->can('update', $payment) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', Rule::in(Payment::types())],
            'direction' => ['sometimes', 'string', Rule::in(Payment::directions())],
            'status' => ['sometimes', 'string', Rule::in(Payment::statuses())],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
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
