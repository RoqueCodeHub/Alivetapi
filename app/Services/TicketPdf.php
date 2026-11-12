<?php

namespace App\Services;

use App\Models\Venta;

class TicketPdf
{
    public function bytes(Venta $venta): string
    {
        $venta->loadMissing(['cliente.persona', 'detalles.producto']);
        $jpeg = $this->logoJpeg();
        $blocks = $this->blocks($venta, $jpeg !== null);
        $height = 18.0;
        foreach ($blocks as $block) {
            $height += $block['h'];
        }
        $height += 16.0;

        $y = $height - 16.0;
        $stream = '';
        foreach ($blocks as $block) {
            $y -= $block['h'];
            if ($block['type'] === 'image') {
                $x = (226 - $block['w']) / 2;
                $stream .= sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /Im1 Do Q\n", $block['w'], $block['h'] - 4, $x, $y + 2);
            } elseif ($block['type'] === 'rule') {
                $stream .= sprintf("0.75 w 16 %.2F m 210 %.2F l S\n", $y + 6, $y + 6);
            } else {
                $size = $block['size'];
                $text = $this->escape($block['text']);
                $x = $block['align'] === 'left'
                    ? 16
                    : max(16, (226 - (strlen($block['text']) * $size * 0.5)) / 2);
                $stream .= sprintf("BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n", $block['font'], $size, $x, $y + 2, $text);
            }
        }

        return $this->document($stream, $height, $jpeg);
    }

    public function filename(Venta $venta): string
    {
        return 'boleta-'.$this->numero($venta).'.pdf';
    }

    private function blocks(Venta $venta, bool $withLogo): array
    {
        $issuer = config('invoicing.issuer');
        $fecha = $venta->created_at?->timezone('America/Lima');
        $total = round((float) $venta->total, 2);
        $descuento = round((float) $venta->descuento, 2);
        $gravado = round($total / 1.18, 2);
        $igv = round($total - $gravado, 2);
        $persona = $venta->cliente?->persona;
        $blocks = [];

        if ($withLogo) {
            $blocks[] = ['type' => 'image', 'h' => 78.0, 'w' => 70.0];
        }
        $blocks[] = $this->line($issuer['razon_social'] ?? 'Veterinaria Ali-Vet', 11, 'F2');
        if (! empty($issuer['address'])) {
            $blocks[] = $this->line((string) $issuer['address'], 8, 'F1');
        }
        $blocks[] = $this->line('RUC '.($issuer['ruc'] ?? ''), 8, 'F1');
        $blocks[] = ['type' => 'rule', 'h' => 10.0];
        $blocks[] = $this->line('BOLETA DE VENTA', 11, 'F2');
        $blocks[] = $this->line($this->numero($venta), 10, 'F2');
        $blocks[] = ['type' => 'rule', 'h' => 10.0];
        $blocks[] = $this->line('CLIENTE', 8, 'F2', 'left');
        $blocks[] = $this->line($venta->cliente?->displayName() ?: 'Cliente varios', 8, 'F1', 'left');
        $documento = trim(($persona->tipo_documento ?? '').' '.($persona->numero_documento ?? ''));
        if ($documento !== '') {
            $blocks[] = $this->line($documento, 8, 'F1', 'left');
        }
        if (! empty($persona?->direccion)) {
            $blocks[] = $this->line((string) $persona->direccion, 8, 'F1', 'left');
        }
        $blocks[] = $this->line('FECHA: '.($fecha?->format('d/m/Y') ?? ''), 8, 'F1', 'left');
        $blocks[] = $this->line('HORA: '.($fecha?->format('H:i:s') ?? ''), 8, 'F1', 'left');
        $blocks[] = $this->line('MONEDA: SOLES', 8, 'F1', 'left');
        $blocks[] = $this->line('IGV: '.rtrim(rtrim(number_format((float) config('invoicing.igv'), 2, '.', ''), '0'), '.').'%', 8, 'F1', 'left');
        $blocks[] = ['type' => 'rule', 'h' => 10.0];
        $blocks[] = $this->line('CANT  DESCRIPCION', 7, 'F2', 'left');

        foreach ($venta->detalles as $detalle) {
            $nombre = $detalle->producto?->nombre ?: 'Producto';
            $blocks[] = $this->line(str_pad((string) $detalle->cantidad, 2, '0', STR_PAD_LEFT).'  '.$nombre, 8, 'F1', 'left');
            $blocks[] = $this->line('P/U '.number_format((float) $detalle->precio_unitario, 2, '.', '').'    TOTAL '.number_format((float) $detalle->subtotal, 2, '.', ''), 8, 'F1', 'left');
        }

        $blocks[] = ['type' => 'rule', 'h' => 10.0];
        if ($descuento > 0) {
            $blocks[] = $this->line('DESCUENTO  S/ '.number_format($descuento, 2, '.', ''), 8, 'F1', 'left');
        }
        $blocks[] = $this->line('GRAVADO  S/ '.number_format($gravado, 2, '.', ''), 8, 'F1', 'left');
        $blocks[] = $this->line('IGV '.rtrim(rtrim(number_format((float) config('invoicing.igv'), 2, '.', ''), '0'), '.').'%  S/ '.number_format($igv, 2, '.', ''), 8, 'F1', 'left');
        $blocks[] = $this->line('TOTAL  S/ '.number_format($total, 2, '.', ''), 11, 'F2', 'left');
        $blocks[] = $this->line($this->enLetras($total), 7, 'F1', 'left');
        $blocks[] = ['type' => 'rule', 'h' => 10.0];
        $blocks[] = $this->line('FORMAS DE PAGO', 8, 'F2', 'left');
        $blocks[] = $this->line($this->pago($venta), 8, 'F1', 'left');
        if ($venta->observaciones) {
            $blocks[] = $this->line($venta->observaciones, 7, 'F1', 'left');
        }
        $blocks[] = ['type' => 'rule', 'h' => 10.0];
        $blocks[] = $this->line('Representacion impresa de boleta de venta', 7, 'F1');
        $blocks[] = $this->line($this->numero($venta), 7, 'F1');

        return $blocks;
    }

    private function line(string $text, float $size, string $font, string $align = 'center'): array
    {
        return ['type' => 'text', 'text' => $text, 'size' => $size, 'font' => $font, 'align' => $align, 'h' => $size + 4];
    }

    private function numero(Venta $venta): string
    {
        return config('invoicing.series', 'B001').'-'.str_pad((string) $venta->id, 6, '0', STR_PAD_LEFT);
    }

    private function pago(Venta $venta): string
    {
        $nombres = [
            'efectivo' => 'Efectivo',
            'yape' => 'Yape',
            'plin' => 'Plin',
            'tarjeta' => 'Tarjeta',
            'transferencia' => 'Transferencia',
        ];
        if ($venta->estado !== 'completada' || ! $venta->metodo_pago) {
            return 'Pendiente de cobro';
        }
        $nombre = $nombres[$venta->metodo_pago] ?? $venta->metodo_pago;
        $monto = number_format((float) ($venta->monto_pagado ?? $venta->total), 2, '.', '');

        return $nombre.'  S/ '.$monto;
    }

    private function enLetras(float $monto): string
    {
        $entero = (int) floor($monto + 0.00001);
        $centavos = (int) round(($monto - $entero) * 100);

        return 'SON: '.$this->letras($entero).' CON '.str_pad((string) $centavos, 2, '0', STR_PAD_LEFT).'/100 SOLES';
    }

    private function letras(int $numero): string
    {
        if ($numero === 0) {
            return 'CERO';
        }
        if ($numero === 100) {
            return 'CIEN';
        }
        $unidades = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE'];
        $especiales = [10 => 'DIEZ', 11 => 'ONCE', 12 => 'DOCE', 13 => 'TRECE', 14 => 'CATORCE', 15 => 'QUINCE', 16 => 'DIECISEIS', 17 => 'DIECISIETE', 18 => 'DIECIOCHO', 19 => 'DIECINUEVE', 20 => 'VEINTE', 21 => 'VEINTIUNO', 22 => 'VEINTIDOS', 23 => 'VEINTITRES', 24 => 'VEINTICUATRO', 25 => 'VEINTICINCO', 26 => 'VEINTISEIS', 27 => 'VEINTISIETE', 28 => 'VEINTIOCHO', 29 => 'VEINTINUEVE'];
        $decenas = ['', '', '', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
        $centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

        $partes = [];
        if ($numero >= 1000) {
            $miles = intdiv($numero, 1000);
            $partes[] = $miles === 1 ? 'MIL' : $this->letras($miles).' MIL';
            $numero %= 1000;
        }
        if ($numero >= 100) {
            $partes[] = $centenas[intdiv($numero, 100)];
            $numero %= 100;
        }
        if ($numero >= 30) {
            $texto = $decenas[intdiv($numero, 10)];
            if ($numero % 10) {
                $texto .= ' Y '.$unidades[$numero % 10];
            }
            $partes[] = $texto;
        } elseif ($numero >= 10) {
            $partes[] = $especiales[$numero];
        } elseif ($numero > 0) {
            $partes[] = $unidades[$numero];
        }

        return trim(implode(' ', array_filter($partes)));
    }

    private function logoJpeg(): ?string
    {
        $path = storage_path('app/brand/logo.webp');
        if (! is_file($path) || ! function_exists('imagecreatefromwebp')) {
            return null;
        }
        $origen = @imagecreatefromwebp($path);
        if (! $origen) {
            return null;
        }
        $ancho = 280;
        $alto = (int) round(imagesy($origen) * ($ancho / imagesx($origen)));
        $lienzo = imagecreatetruecolor($ancho, $alto);
        $blanco = imagecolorallocate($lienzo, 255, 255, 255);
        imagefill($lienzo, 0, 0, $blanco);
        imagecopyresampled($lienzo, $origen, 0, 0, 0, 0, $ancho, $alto, imagesx($origen), imagesy($origen));
        ob_start();
        imagejpeg($lienzo, null, 82);
        $jpeg = ob_get_clean();
        imagedestroy($origen);
        imagedestroy($lienzo);

        return is_string($jpeg) && $jpeg !== '' ? $jpeg : null;
    }

    private function document(string $stream, float $height, ?string $jpeg): string
    {
        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $recursos = '<< /Font << /F1 5 0 R /F2 6 0 R >>';
        if ($jpeg !== null) {
            $recursos .= ' /XObject << /Im1 7 0 R >>';
        }
        $recursos .= ' >>';
        $objects[] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 226 %.2F] /Contents 4 0 R /Resources %s >>', $height, $recursos);
        $objects[] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}endstream";
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
        if ($jpeg !== null) {
            $info = @getimagesizefromstring($jpeg);
            $ancho = $info[0] ?? 280;
            $alto = $info[1] ?? 280;
            $objects[] = "<< /Type /XObject /Subtype /Image /Width {$ancho} /Height {$alto} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ".strlen($jpeg)." >>\nstream\n{$jpeg}\nendstream";
        }

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

    private function escape(string $text): string
    {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = preg_replace('/[^\x20-\x7E]/', ' ', $text) ?? $text;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
