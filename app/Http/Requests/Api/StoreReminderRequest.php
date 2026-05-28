<?php

namespace App\Http\Requests\Api;

use App\Models\Reminder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $remindable = $this->remindable();

        return $remindable instanceof Model
            && ($this->user()?->can('createForRemindable', [Reminder::class, $remindable]) ?? false);
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

    public function remindable(): ?Model
    {
        foreach (['document', 'rental_agreement', 'payment'] as $routeParameter) {
            $remindable = $this->route($routeParameter);

            if ($remindable instanceof Model) {
                return $remindable;
            }
        }

        return null;
    }
}
