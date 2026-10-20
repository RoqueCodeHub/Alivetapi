<?php

namespace App\Support;

class ProductVariantNormalizer
{
    /**
     * @return array{nombre: string, presentation: string}
     */
    public static function split(?string $producto, ?string $presentacion): array
    {
        $producto = trim((string) $producto);
        $presentacion = trim((string) $presentacion);

        if ($presentacion !== '') {
            return [
                'nombre' => $producto !== '' ? $producto : $presentacion,
                'presentation' => $presentacion,
            ];
        }

        if ($producto === '') {
            return ['nombre' => '', 'presentation' => ''];
        }

        if (preg_match('/^(.+?\d+(?:[.,]\d+)?\s*%)\s+(.+)$/u', $producto, $m)) {
            return [
                'nombre' => trim($m[1]),
                'presentation' => trim($m[2]),
            ];
        }

        if (preg_match('/^(.+?)\s+[—–]\s+(.+)$/u', $producto, $m)) {
            return [
                'nombre' => trim($m[1]),
                'presentation' => trim($m[2]),
            ];
        }

        if (preg_match('/^(.+?)\s+(\d+(?:[.,]\d+)?\s*(?:ml|l|kg|g|unid(?:ades)?))\s*$/iu', $producto, $m)) {
            return [
                'nombre' => trim($m[1]),
                'presentation' => trim($m[2]),
            ];
        }

        return [
            'nombre' => $producto,
            'presentation' => $producto,
        ];
    }
}
