<?php

namespace App\Support;

class NativePdfWriter
{
    /**
     * @param list<string> $headers
     * @param iterable<int, list<mixed>> $rows
     */
    public static function bytes(string $title, array $headers, iterable $rows): string
    {
        $lines = [$title, implode(' | ', $headers)];
        foreach ($rows as $row) {
            $lines[] = implode(' | ', array_map(fn ($cell) => (string) $cell, $row));
        }
        $content = '';
        $y = 800;
        foreach ($lines as $line) {
            $safe = self::escape($line);
            $content .= "BT /F1 10 Tf 40 {$y} Td ({$safe}) Tj ET\n";
            $y -= 14;
            if ($y < 40) {
                break;
            }
        }

        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>';
        $objects[] = '<< /Length '.strlen($content)." >>\nstream\n{$content}endstream";
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i => $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$obj}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref\n0 '.(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= 'trailer << /Size '.(count($objects) + 1).' /Root 1 0 R >>\nstartxref\n'.$xref."\n%%EOF";

        return $pdf;
    }

    private static function escape(string $text): string
    {
        $text = preg_replace('/[^\x20-\x7EáéíóúÁÉÍÓÚñÑüÜ.,;:\/\-_|()%S$0-9A-Za-z ]/u', ' ', $text) ?? $text;
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
