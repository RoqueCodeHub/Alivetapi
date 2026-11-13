<?php

namespace App\Services;

use App\Models\Venta;
use GdImage;

class TicketPdf
{
    private const PAGE_WIDTH = 226.0;

    private const MARGIN = 12.0;

    private const WIDTHS = [
        32 => 278, 33 => 278, 34 => 355, 35 => 556, 36 => 556, 37 => 889, 38 => 667, 39 => 191,
        40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
        48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556, 53 => 556, 54 => 556, 55 => 556,
        56 => 556, 57 => 556, 58 => 278, 59 => 278, 60 => 584, 61 => 584, 62 => 584, 63 => 556,
        64 => 1015, 65 => 667, 66 => 667, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778,
        72 => 722, 73 => 278, 74 => 500, 75 => 667, 76 => 556, 77 => 833, 78 => 722, 79 => 778,
        80 => 667, 81 => 778, 82 => 722, 83 => 667, 84 => 611, 85 => 722, 86 => 667, 87 => 944,
        88 => 667, 89 => 667, 90 => 611, 91 => 278, 92 => 278, 93 => 278, 94 => 469, 95 => 556,
        96 => 333, 97 => 556, 98 => 556, 99 => 500, 100 => 556, 101 => 556, 102 => 278, 103 => 556,
        104 => 556, 105 => 222, 106 => 222, 107 => 500, 108 => 222, 109 => 833, 110 => 556, 111 => 556,
        112 => 556, 113 => 556, 114 => 333, 115 => 500, 116 => 278, 117 => 556, 118 => 500, 119 => 722,
        120 => 500, 121 => 500, 122 => 500, 123 => 334, 124 => 260, 125 => 334, 126 => 584,
        161 => 333, 191 => 556,
        193 => 667, 201 => 667, 205 => 278, 209 => 722, 211 => 778, 218 => 722, 220 => 722,
        225 => 556, 233 => 556, 237 => 278, 241 => 556, 243 => 556, 250 => 556, 252 => 556,
    ];

    public function bytes(array $ticket): string
    {
        $jpeg = $this->logoJpeg();
        $logo = null;
        if ($jpeg !== null) {
            $info = @getimagesizefromstring($jpeg);
            $logo = ['width' => $info[0] ?? 280, 'height' => $info[1] ?? 280];
        }

        $blocks = $this->blocks($ticket, $logo);
        $top = 12.0;
        $bottom = 14.0;
        $height = $top + $bottom;
        foreach ($blocks as $block) {
            $height += $block['h'];
        }

        $y = $height - $top;
        $stream = "0 g\n";
        foreach ($blocks as $block) {
            $y -= $block['h'];
            $stream .= $this->paint($block, $y);
        }

        return $this->document($stream, $height, $jpeg);
    }

    public function filename(array $ticket): string
    {
        return 'boleta-'.($ticket['document'] ?? 'ticket').'.pdf';
    }

    public function fromVenta(Venta $venta): array
    {
        $venta->loadMissing(['cliente.persona', 'detalles.producto.categoria']);
        $persona = $venta->cliente?->persona;
        $total = (float) $venta->total;
        $paid = $venta->monto_pagado === null ? $total : (float) $venta->monto_pagado;
        $completed = $venta->estado === 'completada' && (bool) $venta->metodo_pago;

        return [
            'document' => config('invoicing.series', 'B001').'-'.str_pad((string) $venta->id, 6, '0', STR_PAD_LEFT),
            'issued_at' => $venta->created_at?->timezone('America/Lima')->format('Y-m-d H:i:s'),
            'customer_name' => $venta->cliente?->displayName() ?: 'Cliente varios',
            'document_type' => $persona?->tipo_documento,
            'document_number' => $persona?->numero_documento,
            'address' => $persona?->direccion,
            'discount' => (float) ($venta->descuento ?? 0),
            'total' => $total,
            'paid' => $paid,
            'pending' => ! $completed,
            'notes' => $venta->observaciones,
            'payments' => $completed ? [['method' => $venta->metodo_pago, 'amount' => $paid]] : [],
            'items' => $venta->detalles->map(fn ($detalle) => [
                'quantity' => $detalle->cantidad,
                'name' => $detalle->producto?->nombre ?: 'Producto',
                'category' => $detalle->producto?->categoria?->nombre,
                'unit_price' => (float) $detalle->precio_unitario,
                'line_total' => (float) $detalle->subtotal,
            ])->all(),
        ];
    }

    private function blocks(array $ticket, ?array $logo): array
    {
        $issuer = config('invoicing.issuer');
        $fecha = empty($ticket['issued_at']) ? null : \Carbon\Carbon::parse($ticket['issued_at'], 'America/Lima');
        $total = round((float) ($ticket['total'] ?? 0), 2);
        $descuento = round((float) ($ticket['discount'] ?? 0), 2);
        $gravado = round($total / 1.18, 2);
        $igv = round($total - $gravado, 2);
        $document = (string) ($ticket['document'] ?? '');
        $cols = $this->columns();
        $blocks = [];

        if ($logo !== null) {
            $displayW = 56.0;
            $displayH = $displayW * ($logo['height'] / max(1, $logo['width']));
            if ($displayH > 58) {
                $displayH = 58.0;
                $displayW = $displayH * ($logo['width'] / max(1, $logo['height']));
            }
            $blocks[] = ['type' => 'image', 'h' => $displayH + 4, 'w' => $displayW, 'ih' => $displayH];
        }

        $this->addWrapped($blocks, (string) ($issuer['razon_social'] ?? 'Veterinaria Ali-Vet'), 10, 'F2');
        if (! empty($issuer['address'])) {
            $this->addWrapped($blocks, (string) $issuer['address'], 7, 'F1');
        }
        $this->addWrapped($blocks, 'RUC '.($issuer['ruc'] ?? ''), 8, 'F1');
        $phone = trim((string) ($issuer['phone'] ?? ''));
        if ($phone !== '') {
            $this->addWrapped($blocks, 'Central Telefónica: '.$phone, 7, 'F1');
        }

        $blocks[] = $this->rule();
        $blocks[] = $this->line('BOLETA DE VENTA', 10, 'F2');
        $blocks[] = $this->line($document, 9, 'F2');
        $blocks[] = $this->rule();
        $blocks[] = $this->line('CLIENTE', 8, 'F2', 'left');

        $tipo = strtoupper(trim((string) ($ticket['document_type'] ?? '')));
        $documento = trim((string) ($ticket['document_number'] ?? ''));
        if ($documento !== '') {
            $blocks[] = $this->labeled(($tipo !== '' ? $tipo : 'DOC').':', $documento);
        }
        $this->addWrapped($blocks, (string) ($ticket['customer_name'] ?: 'Cliente varios'), 8, 'F1', 'left');
        if (! empty($ticket['address'])) {
            $this->addWrapped($blocks, (string) $ticket['address'], 7.5, 'F1', 'left');
        }

        $blocks[] = $this->gap(3);
        $blocks[] = $this->labeled('FECHA EMISIÓN:', $fecha?->format('d/m/Y') ?? '');
        $blocks[] = $this->labeled('HORA EMISIÓN:', $fecha?->format('H:i:s') ?? '');
        $blocks[] = $this->labeled('MONEDA:', 'SOLES');
        $blocks[] = $this->labeled('IGV:', $this->igvPercent().'%');
        $blocks[] = $this->rule();
        $blocks[] = $this->tableRow('CANT.', 'DESCRIPCIÓN', 'P.U.', 'TOTAL', 7, 'F2');

        foreach ($ticket['items'] ?? [] as $detalle) {
            $nombre = $detalle['name'] ?: 'Producto';
            $categoria = trim((string) ($detalle['category'] ?? ''));
            $descripcion = $this->wrap($nombre, 7.5, false, $cols['desc']['w']);
            $blocks[] = $this->tableRow(
                $this->cantidad($detalle['quantity']),
                $descripcion[0] ?? '',
                number_format((float) $detalle['unit_price'], 2, '.', ''),
                number_format((float) $detalle['line_total'], 2, '.', ''),
                7.5,
                'F1'
            );
            foreach (array_slice($descripcion, 1) as $extra) {
                $blocks[] = $this->line($extra, 7.5, 'F1', 'left', 0, $cols['desc']['x'], $cols['desc']['w']);
            }
            if ($categoria !== '') {
                $this->addWrapped($blocks, $categoria, 6.5, 'F1', 'left', $cols['desc']['w'], 0.4, $cols['desc']['x']);
            }
            $blocks[] = $this->gap(2);
        }

        $blocks[] = $this->rule();
        if ($descuento > 0) {
            $blocks[] = $this->amount('DESCUENTO', $this->money($descuento), 8, 'F1');
        }
        $blocks[] = $this->amount('GRAVADO', $this->money($gravado), 8, 'F1');
        $blocks[] = $this->amount('IGV '.$this->igvPercent().'%', $this->money($igv), 8, 'F1');
        $blocks[] = $this->rule();
        $blocks[] = $this->amount('TOTAL', $this->money($total), 10, 'F2');
        $pagado = array_key_exists('paid', $ticket) ? (float) $ticket['paid'] : $total;
        $saldo = round(max($total - $pagado, 0), 2);
        if ($saldo > 0.009) {
            $blocks[] = $this->amount('SALDO', $this->money($saldo), 9, 'F2');
        }
        $blocks[] = $this->gap(2);
        $this->addWrapped($blocks, $this->enLetras($total), 7, 'F1', 'left');
        $blocks[] = $this->rule();
        $blocks[] = $this->line('FORMAS DE PAGO', 8, 'F2', 'left');
        $this->addWrapped($blocks, $this->pago($ticket), 8, 'F1', 'left');
        if (! empty($ticket['notes'])) {
            $this->addWrapped($blocks, (string) $ticket['notes'], 7, 'F1', 'left');
        }
        $blocks[] = $this->rule();
        $blocks[] = $this->line('REPRESENTACIÓN IMPRESA DE BOLETA DE VENTA', 6.5, 'F1');
        $blocks[] = $this->line($document, 7, 'F2');

        return $blocks;
    }

    private function tableRow(string $qty, string $description, string $price, string $total, float $size, string $font): array
    {
        $cols = $this->columns();

        return [
            'type' => 'row',
            'h' => $size + 2.8,
            'cells' => [
                $this->cell($qty, $cols['qty'], 'left', $size, $font),
                $this->cell($description, $cols['desc'], 'left', $size, $font),
                $this->cell($price, $cols['price'], 'right', $size, $font),
                $this->cell($total, $cols['total'], 'right', $size, $font),
            ],
        ];
    }

    private function cell(string $text, array $box, string $align, float $size, string $font, float $gray = 0): array
    {
        return [
            'text' => $text,
            'x' => $box['x'],
            'w' => $box['w'],
            'align' => $align,
            'size' => $size,
            'font' => $font,
            'gray' => $gray,
        ];
    }

    private function columns(): array
    {
        $right = self::PAGE_WIDTH - self::MARGIN;
        $qtyW = 26.0;
        $priceW = 40.0;
        $totalW = 44.0;
        $gap = 4.0;
        $qtyX = self::MARGIN;
        $descX = $qtyX + $qtyW + $gap;
        $totalX = $right - $totalW;
        $priceX = $totalX - $gap - $priceW;

        return [
            'qty' => ['x' => $qtyX, 'w' => $qtyW],
            'desc' => ['x' => $descX, 'w' => $priceX - $gap - $descX],
            'price' => ['x' => $priceX, 'w' => $priceW],
            'total' => ['x' => $totalX, 'w' => $totalW],
        ];
    }

    private function labeled(string $label, string $value, float $size = 7.5): array
    {
        $labelW = 76.0;
        $valueX = self::MARGIN + $labelW;
        $right = self::PAGE_WIDTH - self::MARGIN;

        return [
            'type' => 'row',
            'h' => $size + 2.8,
            'cells' => [
                $this->cell($label, ['x' => self::MARGIN, 'w' => $labelW], 'left', $size, 'F1'),
                $this->cell($value, ['x' => $valueX, 'w' => $right - $valueX], 'left', $size, 'F1'),
            ],
        ];
    }

    private function amount(string $label, string $value, float $size, string $font): array
    {
        $right = self::PAGE_WIDTH - self::MARGIN;
        $valueW = 78.0;

        return [
            'type' => 'row',
            'h' => $size + 3.2,
            'cells' => [
                $this->cell($label, ['x' => self::MARGIN, 'w' => $right - self::MARGIN - $valueW], 'left', $size, $font),
                $this->cell($value, ['x' => $right - $valueW, 'w' => $valueW], 'right', $size, $font),
            ],
        ];
    }

    private function line(string $text, float $size, string $font, string $align = 'center', float $gray = 0, ?float $x = null, ?float $w = null): array
    {
        $x ??= self::MARGIN;

        return [
            'type' => 'text',
            'text' => $text,
            'size' => $size,
            'font' => $font,
            'align' => $align,
            'h' => $size + 2.8,
            'gray' => $gray,
            'x' => $x,
            'w' => $w ?? (self::PAGE_WIDTH - self::MARGIN - $x),
        ];
    }

    private function rule(): array
    {
        return ['type' => 'rule', 'h' => 8.0];
    }

    private function gap(float $h): array
    {
        return ['type' => 'gap', 'h' => $h];
    }

    private function addWrapped(array &$blocks, string $text, float $size, string $font, string $align = 'center', float $maxWidth = 0, float $gray = 0, ?float $x = null): void
    {
        $width = $maxWidth > 0 ? $maxWidth : $this->contentWidth();
        foreach ($this->wrap($text, $size, $font === 'F2', $width) as $line) {
            $blocks[] = $this->line($line, $size, $font, $align, $gray, $x, $maxWidth > 0 ? $maxWidth : null);
        }
    }

    private function contentWidth(): float
    {
        return self::PAGE_WIDTH - (self::MARGIN * 2);
    }

    private function paint(array $block, float $y): string
    {
        if ($block['type'] === 'gap') {
            return '';
        }
        if ($block['type'] === 'image') {
            $x = (self::PAGE_WIDTH - $block['w']) / 2;

            return sprintf(
                "q %.2F 0 0 %.2F %.2F %.2F cm /Im1 Do Q\n",
                $block['w'],
                $block['ih'],
                $x,
                $y + (($block['h'] - $block['ih']) / 2)
            );
        }
        if ($block['type'] === 'rule') {
            $lineY = $y + ($block['h'] / 2);

            return sprintf(
                "0.8 w %.2F %.2F m %.2F %.2F l S\n",
                self::MARGIN,
                $lineY,
                self::PAGE_WIDTH - self::MARGIN,
                $lineY
            );
        }

        $baseline = $y + 1.2;
        if ($block['type'] === 'row') {
            $stream = '';
            foreach ($block['cells'] as $cell) {
                if ($cell['text'] === '') {
                    continue;
                }
                $stream .= $this->paintText($cell, $this->textX($cell), $baseline);
            }

            return $stream;
        }

        return $this->paintText($block, $this->textX($block), $baseline);
    }

    private function paintText(array $piece, float $x, float $y): string
    {
        $gray = $piece['gray'] ?? 0;
        $stream = $gray > 0 ? sprintf("%.2F g\n", $gray) : '';
        $stream .= sprintf(
            "BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n",
            $piece['font'],
            $piece['size'],
            $x,
            $y,
            $this->escape($piece['text'])
        );

        return $gray > 0 ? $stream."0 g\n" : $stream;
    }

    private function textX(array $piece): float
    {
        $width = $this->textWidth($piece['text'], $piece['size'], $piece['font'] === 'F2');
        if ($piece['align'] === 'center') {
            return max(4, (self::PAGE_WIDTH - $width) / 2);
        }
        if ($piece['align'] === 'right') {
            return $piece['x'] + max(0, $piece['w'] - $width);
        }

        return $piece['x'];
    }

    private function wrap(string $text, float $size, bool $bold, float $maxWidth): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if ($text === '' || $maxWidth <= 0) {
            return [];
        }

        $lines = [];
        $current = '';
        foreach (explode(' ', $text) as $word) {
            foreach ($this->splitWord($word, $size, $bold, $maxWidth) as $piece) {
                $candidate = $current === '' ? $piece : $current.' '.$piece;
                if ($current !== '' && $this->textWidth($candidate, $size, $bold) > $maxWidth) {
                    $lines[] = $current;
                    $current = $piece;

                    continue;
                }
                $current = $candidate;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    private function splitWord(string $word, float $size, bool $bold, float $maxWidth): array
    {
        if ($word === '' || $this->textWidth($word, $size, $bold) <= $maxWidth) {
            return [$word];
        }

        $parts = [];
        $current = '';
        $length = mb_strlen($word);
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($word, $i, 1);
            if ($current !== '' && $this->textWidth($current.$char, $size, $bold) > $maxWidth) {
                $parts[] = $current;
                $current = $char;

                continue;
            }
            $current .= $char;
        }
        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts;
    }

    private function textWidth(string $text, float $size, bool $bold): float
    {
        $encoded = $this->encode($text);
        $units = 0;
        $length = strlen($encoded);
        for ($i = 0; $i < $length; $i++) {
            $byte = ord($encoded[$i]);
            $width = self::WIDTHS[$byte] ?? 500;
            if ($bold && $byte !== 32 && ($byte < 48 || $byte > 57)) {
                $width = (int) round($width * 1.06);
            }
            $units += $width;
        }

        return ($units / 1000) * $size;
    }

    private function pago(array $ticket): string
    {
        $nombres = [
            'efectivo' => 'Efectivo',
            'yape' => 'Yape',
            'plin' => 'Plin',
            'tarjeta' => 'Tarjeta',
            'transferencia' => 'Transferencia',
        ];
        $payments = $ticket['payments'] ?? [];
        if (($ticket['pending'] ?? false) || $payments === []) {
            return 'Pendiente de cobro';
        }

        $parts = [];
        foreach ($payments as $payment) {
            $method = (string) ($payment['method'] ?? '');
            $nombre = $nombres[$method] ?? $method;
            $parts[] = $nombre.' - S/ '.number_format((float) ($payment['amount'] ?? 0), 2, '.', '');
        }

        return implode(' · ', $parts);
    }

    private function money(float $amount): string
    {
        return 'S/ '.number_format($amount, 2, '.', '');
    }

    private function igvPercent(): string
    {
        $formatted = rtrim(rtrim(number_format((float) config('invoicing.igv'), 2, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    private function cantidad(mixed $cantidad): string
    {
        $numero = (float) $cantidad;
        if (abs($numero - round($numero)) < 0.001) {
            return str_pad((string) (int) round($numero), 2, '0', STR_PAD_LEFT);
        }

        return rtrim(rtrim(number_format($numero, 2, '.', ''), '0'), '.');
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
        if ($origen === false) {
            return null;
        }
        $plano = $this->flattenOnWhite($origen);
        imagedestroy($origen);

        $ancho = 280;
        $alto = max(1, (int) round(imagesy($plano) * ($ancho / max(1, imagesx($plano)))));
        $lienzo = imagecreatetruecolor($ancho, $alto);
        imagecopyresampled($lienzo, $plano, 0, 0, 0, 0, $ancho, $alto, imagesx($plano), imagesy($plano));
        imagedestroy($plano);
        ob_start();
        imagejpeg($lienzo, null, 85);
        $jpeg = ob_get_clean();
        imagedestroy($lienzo);

        return is_string($jpeg) && $jpeg !== '' ? $jpeg : null;
    }

    private function flattenOnWhite(GdImage $source): GdImage
    {
        if (! imageistruecolor($source)) {
            imagepalettetotruecolor($source);
        }
        imagealphablending($source, false);
        imagesavealpha($source, true);

        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        $white = imagecolorallocate($canvas, 255, 255, 255);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($source, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                if ($alpha > 0) {
                    $opacity = 1 - ($alpha / 127);
                    $r = (int) round($r * $opacity + 255 * (1 - $opacity));
                    $g = (int) round($g * $opacity + 255 * (1 - $opacity));
                    $b = (int) round($b * $opacity + 255 * (1 - $opacity));
                }
                imagesetpixel($canvas, $x, $y, ($r << 16) | ($g << 8) | $b);
            }
        }

        $seen = array_fill(0, $height, array_fill(0, $width, false));
        $queue = new \SplQueue;
        $consider = function (int $x, int $y) use (&$seen, $queue, $canvas, $width, $height): void {
            if ($x < 0 || $y < 0 || $x >= $width || $y >= $height || $seen[$y][$x]) {
                return;
            }
            $seen[$y][$x] = true;
            $rgba = imagecolorat($canvas, $x, $y);
            if (! $this->isMatte(($rgba >> 16) & 0xFF, ($rgba >> 8) & 0xFF, $rgba & 0xFF)) {
                return;
            }
            $queue->enqueue([$x, $y]);
        };

        for ($x = 0; $x < $width; $x++) {
            $consider($x, 0);
            $consider($x, $height - 1);
        }
        for ($y = 0; $y < $height; $y++) {
            $consider(0, $y);
            $consider($width - 1, $y);
        }

        while (! $queue->isEmpty()) {
            [$x, $y] = $queue->dequeue();
            imagesetpixel($canvas, $x, $y, $white);
            $consider($x + 1, $y);
            $consider($x - 1, $y);
            $consider($x, $y + 1);
            $consider($x, $y - 1);
        }

        return $canvas;
    }

    private function isMatte(int $r, int $g, int $b): bool
    {
        $luma = (int) round(($r * 299 + $g * 587 + $b * 114) / 1000);

        return (max($r, $g, $b) - min($r, $g, $b)) <= 28 && $luma < 245;
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
        $objects[] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Contents 4 0 R /Resources %s >>', self::PAGE_WIDTH, $height, $recursos);
        $objects[] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}endstream";
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
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
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $this->encode($text));
    }

    private function encode(string $text): string
    {
        if ($text === '') {
            return '';
        }
        $encoded = iconv('UTF-8', 'Windows-1252//IGNORE', $text);
        if (is_string($encoded)) {
            return $encoded;
        }
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        return is_string($ascii) ? $ascii : $text;
    }
}
