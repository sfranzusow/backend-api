<?php

namespace App\Http\Requests\Api;

use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\RentalAgreement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRentalAgreementDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var RentalAgreement $rentalAgreement */
        $rentalAgreement = $this->route('rental_agreement');

        return $this->user()?->can('createForRentalAgreement', [Document::class, $rentalAgreement]) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document_template_id' => ['nullable', 'integer', Rule::exists('document_templates', 'id')],
            'document_type' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! $this->filled('document_template_id')) {
                    return;
                }

                $template = DocumentTemplate::query()->find($this->integer('document_template_id'));

                if ($template !== null && $template->document_type !== $this->string('document_type')->toString()) {
                    $validator->errors()->add(
                        'document_template_id',
                        'The selected document template must match the document type.'
                    );
                }
            },
        ];
    }
}
