<?php

namespace App\Support;

class CatalogCategoryResolver
{
    /**
     * @return array{nombre: string, parent: ?string}
     */
    public static function resolve(string $excelCategory, string $productName = '', string $presentation = ''): array
    {
        $key = self::normalize($excelCategory);
        $haystack = self::normalize($productName.' '.$presentation);

        return match (true) {
            $key === 'antibioticos' => ['nombre' => 'Antibióticos', 'parent' => 'Farmacia veterinaria'],
            $key === 'antiinflamatorios' => ['nombre' => 'Antiinflamatorios y analgésicos', 'parent' => 'Farmacia veterinaria'],
            $key === 'antiparasitarios' => ['nombre' => 'Antiparasitarios internos', 'parent' => 'Farmacia veterinaria'],
            str_contains($key, 'aves') || str_contains($key, 'cuyes') => ['nombre' => 'Antiparasitarios internos', 'parent' => 'Farmacia veterinaria'],
            str_contains($key, 'shampoo') => ['nombre' => 'Antiparasitarios externos', 'parent' => 'Farmacia veterinaria'],
            str_contains($key, 'vitaminas') => ['nombre' => 'Vitaminas', 'parent' => 'Suplementos y nutrición'],
            str_contains($key, 'accesorios') => self::resolveAccessory($haystack),
            str_contains($key, 'ropa') || str_contains($key, 'camas') => self::resolveClothing($haystack),
            $key === 'medicamentos' => ['nombre' => 'Farmacia veterinaria', 'parent' => null],
            default => ['nombre' => trim($excelCategory), 'parent' => null],
        };
    }

    /**
     * @return array{nombre: string, parent: ?string}
     */
    private static function resolveClothing(string $haystack): array
    {
        if (preg_match('/cama|colch|manta|cojin|almohad|casa|refugio/', $haystack)) {
            return ['nombre' => 'Camas y descanso', 'parent' => 'Accesorios'];
        }

        return ['nombre' => 'Ropa', 'parent' => 'Accesorios'];
    }

    /**
     * @return array{nombre: string, parent: ?string}
     */
    private static function resolveAccessory(string $haystack): array
    {
        return match (true) {
            str_contains($haystack, 'collar') => ['nombre' => 'Collares', 'parent' => 'Accesorios'],
            str_contains($haystack, 'correa') || str_contains($haystack, 'tiro') => ['nombre' => 'Correas y tiros', 'parent' => 'Accesorios'],
            str_contains($haystack, 'arnes') || str_contains($haystack, 'pechera') => ['nombre' => 'Arneses y pecheras', 'parent' => 'Accesorios'],
            str_contains($haystack, 'bozal') => ['nombre' => 'Bozales', 'parent' => 'Accesorios'],
            str_contains($haystack, 'plato') || str_contains($haystack, 'bebed') => ['nombre' => 'Platos y bebederos', 'parent' => 'Accesorios'],
            default => ['nombre' => 'Otros accesorios', 'parent' => 'Accesorios'],
        };
    }

    private static function normalize(string $value): string
    {
        $text = mb_strtolower(trim($value));
        $text = strtr($text, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);

        return $text;
    }
}
