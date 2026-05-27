<?php

namespace App\Services\Documents;

class DocumentTemplateRenderer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function render(?string $content, array $data, ?string $defaultContent = '<h1>{{ document.title }}</h1>'): string
    {
        $templateContent = $content !== null && $content !== ''
            ? $content
            : ($defaultContent ?? '');

        return preg_replace_callback(
            '/{{\s*([A-Za-z0-9_.]+)\s*}}/',
            fn (array $matches): string => e($this->stringValue(data_get($data, $matches[1]))),
            $templateContent
        ) ?? $templateContent;
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
