<?php

namespace App\Support;

class ProductNameSplitter
{
    /**
     * @return array{nombre: string, presentacion: ?string, talla: ?string, unidad_medida: ?string}
     */
    public static function split(string $raw, ?string $presentacion = null, ?string $talla = null, ?string $unidad = null): array
    {
        $nombre = self::clean($raw);
        $presentacion = self::clean((string) $presentacion);
        $talla = self::normalizeSize($talla) ?? self::extractSize($nombre);
        if ($talla && $presentacion === '') {
            $nombre = self::stripSize($nombre, $talla);
        }

        if ($presentacion === '') {
            [$nombre, $presentacion] = self::extractPresentation($nombre);
        } else {
            $nombre = self::stripPresentation($nombre, $presentacion);
        }

        $unidad = self::normalizeUnit($unidad ?: self::unitFromPresentation($presentacion));

        return [
            'nombre' => self::title($nombre !== '' ? $nombre : $raw),
            'presentacion' => $presentacion !== '' ? self::normalizePresentation($presentacion) : null,
            'talla' => $talla,
            'unidad_medida' => $unidad,
        ];
    }

    public static function clean(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        return $value;
    }

    public static function title(string $value): string
    {
        $value = mb_strtolower(self::clean($value));
        $parts = preg_split('/\s+/u', $value) ?: [];
        $small = ['de', 'del', 'la', 'el', 'y', 'en', 'para', 'con'];
        $out = [];
        foreach ($parts as $i => $part) {
            if ($i > 0 && in_array($part, $small, true)) {
                $out[] = $part;
                continue;
            }
            $out[] = mb_strtoupper(mb_substr($part, 0, 1)).mb_substr($part, 1);
        }

        return implode(' ', $out);
    }

    public static function normalizeSize(?string $value): ?string
    {
        $raw = self::clean((string) $value);
        if ($raw === '') {
            return null;
        }
        $map = [
            'xs' => 'XS',
            's' => 'S',
            'm' => 'M',
            'l' => 'L',
            'xl' => 'XL',
            'xxl' => 'XXL',
            'p' => 'P',
            'g' => 'G',
            'pequeño' => 'Pequeño',
            'pequeno' => 'Pequeño',
            'mediano' => 'Mediano',
            'grande' => 'Grande',
        ];
        $key = mb_strtolower($raw);

        return $map[$key] ?? $raw;
    }

    public static function normalizeUnit(?string $unit): ?string
    {
        $raw = mb_strtolower(self::clean((string) $unit));
        if ($raw === '') {
            return null;
        }
        $map = [
            'ml' => 'ml',
            'cc' => 'ml',
            'cm3' => 'ml',
            'l' => 'L',
            'lt' => 'L',
            'litro' => 'L',
            'litros' => 'L',
            'g' => 'g',
            'gr' => 'g',
            'gramos' => 'g',
            'kg' => 'kg',
            'kilo' => 'kg',
            'unidad' => 'unidad',
            'unid' => 'unidad',
            'unidades' => 'unidades',
            'tableta' => 'tableta',
            'tabletas' => 'tableta',
            'tab' => 'tableta',
            'capsula' => 'cápsula',
            'cápsula' => 'cápsula',
            'capsulas' => 'cápsula',
            'frasco' => 'frasco',
            'ampolla' => 'ampolla',
            'ampollas' => 'ampolla',
            'sobre' => 'sobre',
            'sobres' => 'sobre',
            'rollo' => 'rollo',
            'par' => 'par',
            'metro' => 'metro',
            'm' => 'metro',
        ];

        return $map[$raw] ?? $raw;
    }

    private static function extractSize(string $nombre): ?string
    {
        if (preg_match('/\b(?:talla\s+)?(xxl|xl|xs|s|m|l|p|g|pequeño|pequeno|mediano|grande)\b$/iu', $nombre, $m)) {
            return self::normalizeSize($m[1]);
        }

        return null;
    }

    private static function stripSize(string $nombre, string $talla): string
    {
        $pattern = '/(?:\s+talla)?\s+'.preg_quote($talla, '/').'$/iu';

        return self::clean(preg_replace($pattern, '', $nombre) ?? $nombre);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function extractPresentation(string $nombre): array
    {
        if (preg_match('/^(.+?)\s+(\d+(?:[.,]\d+)?\s*(?:ml|l|g|kg|unidades|unidad|tabs?|tabletas?|cápsulas?|capsulas?|ampollas?|frascos?|sobres?))\s*$/iu', $nombre, $m)) {
            return [self::clean($m[1]), self::normalizePresentation($m[2])];
        }
        if (preg_match('/^(.+?)\s+(\d+(?:[.,]\d+)?\s*%)\s*$/u', $nombre, $m)) {
            return [self::clean($m[1]), self::clean($m[2])];
        }

        return [$nombre, ''];
    }

    private static function stripPresentation(string $nombre, string $presentacion): string
    {
        $stripped = self::clean(str_ireplace($presentacion, '', $nombre));

        return $stripped !== '' ? $stripped : $nombre;
    }

    private static function normalizePresentation(string $value): string
    {
        $value = self::clean($value);
        $value = preg_replace('/\bML\b/i', 'ml', $value) ?? $value;
        $value = preg_replace('/\bLTS?\b/i', 'L', $value) ?? $value;

        return $value;
    }

    private static function unitFromPresentation(?string $presentacion): ?string
    {
        if (! $presentacion) {
            return null;
        }
        if (preg_match('/\b(ml|l|g|kg|unidades?|tabletas?|cápsulas?|capsulas?|ampollas?|frascos?|sobres?|metro)\b/iu', $presentacion, $m)) {
            return self::normalizeUnit($m[1]);
        }

        return null;
    }
}
