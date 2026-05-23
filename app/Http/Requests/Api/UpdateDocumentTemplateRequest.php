<?php

namespace App\Http\Requests\Api;

use App\Models\DocumentTemplate;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateDocumentTemplateRequest extends StoreDocumentTemplateRequest
{
    public function authorize(): bool
    {
        $documentTemplate = $this->route('document_template');

        return $documentTemplate instanceof DocumentTemplate
            && ($this->user()?->can('update', $documentTemplate) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->templateRules(isUpdate: true);
    }
}
