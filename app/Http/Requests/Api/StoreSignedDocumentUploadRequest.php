<?php

namespace App\Http\Requests\Api;

use App\Models\Document;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreSignedDocumentUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Document $document */
        $document = $this->route('document');

        return $this->user()?->can('uploadSigned', $document) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max('10mb')],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
