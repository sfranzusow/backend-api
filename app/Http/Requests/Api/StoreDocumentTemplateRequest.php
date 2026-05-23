<?php

namespace App\Http\Requests\Api;

use App\Models\DocumentTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDocumentTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', DocumentTemplate::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->templateRules();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function templateRules(bool $isUpdate = false): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255'],
            'document_type' => [$required, 'string', Rule::in(DocumentTemplate::documentTypes())],
            'template_type' => ['sometimes', 'string', 'max:255'],
            'locale' => ['sometimes', 'string', 'max:10'],
            'version' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', 'string', Rule::in(DocumentTemplate::statuses())],
            'content' => ['nullable', 'string'],
            'placeholders' => ['nullable', 'array'],
            'placeholders.*' => ['string', 'max:255'],
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
                $this->validateTemplatePayload($validator);
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function templateAttributes(): array
    {
        $attributes = $this->validated();

        if (! array_key_exists('placeholders', $attributes)) {
            $content = $attributes['content'] ?? $this->documentTemplate()?->content ?? null;
            $attributes['placeholders'] = DocumentTemplate::extractPlaceholders($content);
        }

        return $attributes;
    }

    protected function documentTemplate(): ?DocumentTemplate
    {
        $documentTemplate = $this->route('document_template');

        return $documentTemplate instanceof DocumentTemplate ? $documentTemplate : null;
    }

    protected function validateTemplatePayload(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $documentType = $this->effectiveValue('document_type');
        $templateType = $this->effectiveValue('template_type', 'default');
        $locale = $this->effectiveValue('locale', 'de-DE');
        $version = (int) $this->effectiveValue('version', 1);
        $content = $this->effectiveValue('content');
        $declaredPlaceholders = $this->effectivePlaceholders($content);
        $contentPlaceholders = DocumentTemplate::extractPlaceholders($content);
        $allowedPlaceholders = DocumentTemplate::allowedPlaceholdersFor((string) $documentType);
        $unknownPlaceholders = array_values(array_diff(
            array_values(array_unique([...$declaredPlaceholders, ...$contentPlaceholders])),
            $allowedPlaceholders
        ));

        if ($unknownPlaceholders !== []) {
            $validator->errors()->add(
                'placeholders',
                'Unknown placeholders: '.implode(', ', $unknownPlaceholders)
            );
        }

        $existingTemplate = DocumentTemplate::query()
            ->where('document_type', $documentType)
            ->where('template_type', $templateType)
            ->where('locale', $locale)
            ->where('version', $version)
            ->when($this->documentTemplate(), fn ($query, DocumentTemplate $template) => $query->whereKeyNot($template->id))
            ->exists();

        if ($existingTemplate) {
            $validator->errors()->add(
                'version',
                'A document template with this document type, template type, locale and version already exists.'
            );
        }
    }

    private function effectiveValue(string $key, mixed $fallback = null): mixed
    {
        if ($this->has($key)) {
            return $this->input($key);
        }

        $template = $this->documentTemplate();

        return $template?->{$key} ?? $fallback;
    }

    /**
     * @return list<string>
     */
    private function effectivePlaceholders(mixed $content): array
    {
        if ($this->has('placeholders')) {
            return array_values(array_unique(array_map(
                static fn (mixed $placeholder): string => (string) $placeholder,
                $this->input('placeholders', [])
            )));
        }

        $template = $this->documentTemplate();

        return $template?->placeholders ?? DocumentTemplate::extractPlaceholders(is_string($content) ? $content : null);
    }
}
