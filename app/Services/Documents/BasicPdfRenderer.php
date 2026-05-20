<?php

namespace App\Services\Documents;

use Illuminate\Support\Str;

class BasicPdfRenderer
{
    public function render(string $content): string
    {
        $plainText = html_entity_decode(
            strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />', '</h1>', '</h2>'], "\n", $content)),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $plainText = Str::of($plainText)
            ->replaceMatches('/[ \t]+/', ' ')
            ->replaceMatches("/\n{3,}/", "\n\n")
            ->trim()
            ->toString();

        $lines = [];

        foreach (explode("\n", Str::ascii($plainText)) as $line) {
            foreach (explode("\n", wordwrap($line, 88, "\n", true)) as $wrappedLine) {
                $lines[] = $wrappedLine;
            }
        }

        $stream = "BT\n/F1 11 Tf\n50 790 Td\n14 TL\n";

        foreach (array_slice($lines, 0, 52) as $line) {
            $stream .= '('.$this->escapeText($line).") Tj\nT*\n";
        }

        $stream .= 'ET';

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj",
            "4 0 obj\n<< /Length ".strlen($stream)." >>\nstream\n".$stream."\nendstream\nendobj",
            "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n".$xrefOffset."\n%%EOF\n";

        return $pdf;
    }

    private function escapeText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
