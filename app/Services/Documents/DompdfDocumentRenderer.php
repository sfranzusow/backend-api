<?php

namespace App\Services\Documents;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfPdf;
use Dompdf\Dompdf;
use Illuminate\Filesystem\Filesystem;

class DompdfDocumentRenderer
{
    public function __construct(private Filesystem $files) {}

    public function render(string $content): string
    {
        $this->ensureFontDirectoriesExist();

        $pdf = Pdf::loadView('pdf.document', [
            'content' => $content,
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
        $this->addPageNumbers($pdf);

        return $pdf->output();
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
