<?php

namespace App\Services\Documents;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfPdf;
use Dompdf\Dompdf;
use Illuminate\Filesystem\Filesystem;

class DompdfDocumentRenderer
{
    public function __construct(private Filesystem $files) {}

    /**
     * @param  array{header?: array{enabled?: bool, content?: ?string, banner_path?: ?string}, footer?: array{enabled?: bool, content?: ?string, banner_path?: ?string, page_numbers_enabled?: bool}}|null  $layout
     */
    public function render(string $content, ?array $layout = null): string
    {
        $this->ensureFontDirectoriesExist();
        $header = $this->layoutSection($layout['header'] ?? null);
        $footer = $this->layoutSection($layout['footer'] ?? null);
        $pageNumbersEnabled = ($layout['footer']['enabled'] ?? false) === true
            && ($layout['footer']['page_numbers_enabled'] ?? false) === true;
        $hasHeader = $this->hasSectionContent($header);
        $hasFooter = $this->hasSectionContent($footer) || $pageNumbersEnabled;

        $pdf = Pdf::loadView('pdf.document', [
            'content' => $content,
            'headerContent' => $header['content'],
            'footerContent' => $footer['content'],
            'headerBannerPath' => $header['banner_path'],
            'footerBannerPath' => $footer['banner_path'],
            'hasHeader' => $hasHeader,
            'hasFooter' => $hasFooter,
        ])
            ->setPaper('a4', 'portrait')
            ->setWarnings(false)
            ->setOption([
                'default_font' => 'DejaVu Sans',
                'enable_javascript' => false,
                'enable_php' => false,
                'enable_remote' => false,
            ]);

        $pdf->render();

        if ($pageNumbersEnabled) {
            $this->addPageNumbers($pdf);
        }

        return $pdf->output();
    }

    /**
     * @param  array{enabled?: bool, content?: ?string, banner_path?: ?string}|null  $section
     * @return array{content: ?string, banner_path: ?string}
     */
    private function layoutSection(?array $section): array
    {
        if (($section['enabled'] ?? false) !== true) {
            return [
                'content' => null,
                'banner_path' => null,
            ];
        }

        return [
            'content' => $this->nonEmptyString($section['content'] ?? null),
            'banner_path' => $this->publicBannerPath($section['banner_path'] ?? null),
        ];
    }

    /**
     * @param  array{content: ?string, banner_path: ?string}  $section
     */
    private function hasSectionContent(array $section): bool
    {
        return $section['content'] !== null || $section['banner_path'] !== null;
    }

    private function nonEmptyString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function publicBannerPath(mixed $path): ?string
    {
        $path = $this->nonEmptyString($path);

        if ($path === null) {
            return null;
        }

        $fullPath = public_path(ltrim($path, '/'));

        return $this->files->isFile($fullPath) ? $fullPath : null;
    }

    private function addPageNumbers(DompdfPdf $pdf): void
    {
        $dompdf = $pdf->getDomPDF();
        $font = $this->footerFont($dompdf);

        if ($font === null) {
            return;
        }

        $dompdf->getCanvas()->page_text(
            252,
            812,
            'Seite {PAGE_NUM} von {PAGE_COUNT}',
            $font,
            8,
            [0.42, 0.42, 0.42]
        );
    }

    private function footerFont(Dompdf $dompdf): ?string
    {
        $fontMetrics = $dompdf->getFontMetrics();

        return $fontMetrics->getFont('DejaVu Sans', 'normal')
            ?? $fontMetrics->getFont(null, 'normal');
    }

    private function ensureFontDirectoriesExist(): void
    {
        foreach (['font_dir', 'font_cache'] as $key) {
            $path = config('dompdf.options.'.$key);

            if (is_string($path) && $path !== '') {
                $this->files->ensureDirectoryExists($path);
            }
        }
    }
}
