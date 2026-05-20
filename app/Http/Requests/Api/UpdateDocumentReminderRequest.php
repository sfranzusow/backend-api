<?php

namespace App\Http\Requests\Api;

use App\Models\DocumentReminder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDocumentReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var DocumentReminder $documentReminder */
        $documentReminder = $this->route('document_reminder');

        return $this->user()?->can('update', $documentReminder) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'due_at' => ['sometimes', 'date'],
            'remind_at' => ['nullable', 'date'],
            'status' => ['sometimes', 'string', Rule::in(DocumentReminder::statuses())],
            'assigned_to_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'metadata' => ['nullable', 'array'],
            'completed_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $documentReminder = $this->route('document_reminder');

                if (! $documentReminder instanceof DocumentReminder) {
                    return;
                }

                $dueAt = $this->input('due_at', $documentReminder->due_at);
                $remindAt = $this->input('remind_at', $documentReminder->remind_at);

                if ($dueAt === null || $remindAt === null) {
                    return;
                }

                if (Carbon::parse($remindAt)->greaterThan(Carbon::parse($dueAt))) {
                    $validator->errors()->add('remind_at', 'The remind at date must be before or equal to the due at date.');
                }
            },
        ];
    }
}
