<?php

namespace App\Http\Requests\Api;

use App\Enums\RoleName;
use App\Models\DocumentLayoutTemplate;
use App\Models\DocumentTemplate;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDocumentLayoutTemplateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $authUser = $this->user();

        if (
            ! ($this->documentLayoutTemplate() instanceof DocumentLayoutTemplate)
            && $authUser?->hasRole(RoleName::Landlord->value) === true
            && ! $this->has('owner_type')
            && ! $this->has('owner_id')
        ) {
            $this->merge([
                'owner_type' => $authUser->organization_id === null
                    ? DocumentLayoutTemplate::OWNER_TYPE_USER
                    : DocumentLayoutTemplate::OWNER_TYPE_ORGANIZATION,
                'owner_id' => $authUser->organization_id ?? $authUser->id,
            ]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', DocumentLayoutTemplate::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->layoutRules();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function layoutRules(bool $isUpdate = false): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';

        return [
            'owner_type' => [$required, 'string', Rule::in(DocumentLayoutTemplate::ownerTypes())],
            'owner_id' => [$required, 'integer', 'min:1'],
            'name' => [$required, 'string', 'max:255'],
            'document_type' => [$required, 'string', Rule::in(DocumentTemplate::documentTypes())],
            'locale' => ['sometimes', 'string', 'max:10'],
            'version' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', 'string', Rule::in(DocumentLayoutTemplate::statuses())],
            'header_enabled' => ['sometimes', 'boolean'],
            'footer_enabled' => ['sometimes', 'boolean'],
            'page_numbers_enabled' => ['sometimes', 'boolean'],
            'header_content' => ['nullable', 'string'],
            'footer_content' => ['nullable', 'string'],
            'header_banner_path' => ['nullable', 'string', 'max:2048'],
            'footer_banner_path' => ['nullable', 'string', 'max:2048'],
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
                $this->validateLayoutPayload($validator);
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function layoutAttributes(): array
    {
        $attributes = $this->validated();

        if (array_key_exists('owner_type', $attributes)) {
            $attributes['owner_type'] = DocumentLayoutTemplate::ownerClassFor($attributes['owner_type']);
        }

        if (! array_key_exists('placeholders', $attributes)) {
            $attributes['placeholders'] = $this->effectivePlaceholders(
                $this->effectiveString('header_content'),
                $this->effectiveString('footer_content')
            );
        }

        return $attributes;
    }

    protected function documentLayoutTemplate(): ?DocumentLayoutTemplate
    {
        $documentLayoutTemplate = $this->route('document_layout_template');

        return $documentLayoutTemplate instanceof DocumentLayoutTemplate ? $documentLayoutTemplate : null;
    }

    protected function validateLayoutPayload(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $ownerType = DocumentLayoutTemplate::ownerClassFor((string) $this->effectiveValue('owner_type'));
        $ownerId = (int) $this->effectiveValue('owner_id');
        $documentType = (string) $this->effectiveValue('document_type');
        $locale = (string) $this->effectiveValue('locale', 'de-DE');
        $version = (int) $this->effectiveValue('version', 1);

        $this->validateOwner($validator, $ownerType, $ownerId);
        $this->validatePlaceholders($validator, $documentType);
        $this->validateUniqueVersion($validator, $ownerType, $ownerId, $documentType, $locale, $version);
    }

    private function validateOwner(Validator $validator, string $ownerType, int $ownerId): void
    {
        $ownerExists = match ($ownerType) {
            Organization::class => Organization::query()->whereKey($ownerId)->exists(),
            User::class => User::query()->whereKey($ownerId)->exists(),
        };

        if (! $ownerExists) {
            $validator->errors()->add('owner_id', 'The selected layout owner does not exist.');

            return;
        }

        $authUser = $this->user();

        if (
            $authUser instanceof User
            && ! $authUser->hasRole(RoleName::Admin->value)
            && ! $this->ownerBelongsToLandlord($authUser, $ownerType, $ownerId)
        ) {
            $validator->errors()->add('owner_id', 'The selected layout owner is not available for this user.');
        }
    }

    private function validatePlaceholders(Validator $validator, string $documentType): void
    {
        $declaredPlaceholders = $this->effectivePlaceholders(
            $this->effectiveString('header_content'),
            $this->effectiveString('footer_content')
        );
        $allowedPlaceholders = DocumentTemplate::allowedPlaceholdersFor($documentType);
        $unknownPlaceholders = array_values(array_diff($declaredPlaceholders, $allowedPlaceholders));

        if ($unknownPlaceholders !== []) {
            $validator->errors()->add(
                'placeholders',
                'Unknown placeholders: '.implode(', ', $unknownPlaceholders)
            );
        }
    }

    private function validateUniqueVersion(
        Validator $validator,
        string $ownerType,
        int $ownerId,
        string $documentType,
        string $locale,
        int $version
    ): void {
        $existingLayout = DocumentLayoutTemplate::query()
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->where('document_type', $documentType)
            ->where('locale', $locale)
            ->where('version', $version)
            ->when($this->documentLayoutTemplate(), fn ($query, DocumentLayoutTemplate $layout) => $query->whereKeyNot($layout->id))
            ->exists();

        if ($existingLayout) {
            $validator->errors()->add(
                'version',
                'A document layout template with this owner, document type, locale and version already exists.'
            );
        }
    }

    private function ownerBelongsToLandlord(User $authUser, string $ownerType, int $ownerId): bool
    {
        if ($ownerType === Organization::class) {
            return $authUser->organization_id !== null
                && (int) $authUser->organization_id === $ownerId;
        }

        return $ownerType === User::class
            && (int) $authUser->id === $ownerId;
    }

    private function effectiveValue(string $key, mixed $fallback = null): mixed
    {
        if ($this->has($key)) {
            return $this->input($key);
        }

        $layout = $this->documentLayoutTemplate();

        return $layout?->{$key} ?? $fallback;
    }

    private function effectiveString(string $key): ?string
    {
        $value = $this->effectiveValue($key);

        return is_string($value) ? $value : null;
    }

    /**
     * @return list<string>
     */
    private function effectivePlaceholders(?string $headerContent, ?string $footerContent): array
    {
        if ($this->has('placeholders')) {
            return array_values(array_unique(array_map(
                static fn (mixed $placeholder): string => (string) $placeholder,
                $this->input('placeholders', [])
            )));
        }

        if ($this->has('header_content') || $this->has('footer_content')) {
            return DocumentLayoutTemplate::extractLayoutPlaceholders($headerContent, $footerContent);
        }

        $layout = $this->documentLayoutTemplate();

        return $layout?->placeholders
            ?? DocumentLayoutTemplate::extractLayoutPlaceholders($headerContent, $footerContent);
    }
}
