<?php

namespace App\Http\Requests\Api;

use App\Models\DocumentLayoutTemplate;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateDocumentLayoutTemplateRequest extends StoreDocumentLayoutTemplateRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $documentLayoutTemplate = $this->route('document_layout_template');

        return $documentLayoutTemplate instanceof DocumentLayoutTemplate
            && ($this->user()?->can('update', $documentLayoutTemplate) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->layoutRules(isUpdate: true);
    }
}
