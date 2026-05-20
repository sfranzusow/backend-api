<?php

namespace App\Http\Requests\Api;

use App\Models\Document;
use App\Models\DocumentReminder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Document $document */
        $document = $this->route('document');

        return $this->user()?->can('createForDocument', [DocumentReminder::class, $document]) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'due_at' => ['required', 'date'],
            'remind_at' => ['nullable', 'date', 'before_or_equal:due_at'],
            'assigned_to_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
