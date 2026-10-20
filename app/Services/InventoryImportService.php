<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Variante;
use App\Support\CatalogCategoryResolver;
use App\Support\InventoryDateParser;
use App\Support\InventorySpreadsheetReader;
use App\Support\ProductVariantNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryImportService
{
    public function __construct(
        private readonly InventorySpreadsheetReader $reader,
        private readonly StockService $stock,
    ) {
    }

    /**
     * @return array{
     *     processed: int,
     *     imported: int,
     *     rejected: int,
     *     pending_review: int,
     *     errors: list<array{row: int, message: string}>,
     *     created: array{categories: int, products: int, variants: int, batches: int, movements: int}
     * }
     */
    public function import(UploadedFile $file, User $user): array
    {
        $rows = $this->reader->read($file);
        $summary = [
            'processed' => 0,
            'imported' => 0,
            'rejected' => 0,
            'pending_review' => 0,
            'errors' => [],
            'created' => [
                'categories' => 0,
                'products' => 0,
                'variants' => 0,
                'batches' => 0,
                'movements' => 0,
            ],
        ];

        $marca = Marca::query()->firstOrCreate(['nombre' => 'Genérico'], ['activo' => true]);

        foreach ($rows as $index => $row) {
            $excelRow = $index + 2;
            $summary['processed']++;

            try {
                $result = DB::transaction(function () use ($row, $excelRow, $user, $marca) {
                    return $this->importRow($row, $excelRow, $user, $marca);
                });
            } catch (\Throwable $e) {
                $summary['rejected']++;
                $summary['errors'][] = [
                    'row' => $excelRow,
                    'message' => $e->getMessage(),
                ];
                continue;
            }

            $summary['imported']++;
            if ($result['needs_review']) {
                $summary['pending_review']++;
            }
            foreach ($result['created'] as $key => $count) {
                $summary['created'][$key] += $count;
            }
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{needs_review: bool, created: array<string, int>}
     */
    private function importRow(array $row, int $excelRow, User $user, Marca $marca): array
    {
        $created = [
            'categories' => 0,
            'products' => 0,
            'variants' => 0,
            'batches' => 0,
            'movements' => 0,
        ];

        $productNameRaw = trim((string) ($row['producto'] ?? ''));
        if ($productNameRaw === '') {
            throw new \InvalidArgumentException('Falta el nombre del producto.');
        }

        $split = ProductVariantNormalizer::split($productNameRaw, $row['presentacion'] ?? null);
        if ($split['nombre'] === '') {
            throw new \InvalidArgumentException('Falta el nombre del producto.');
        }

        $categoryName = trim((string) ($row['categoria'] ?? ''));
        $category = $this->findOrCreateCategory($categoryName, $split['nombre'], $split['presentation']);
        if ($category->wasRecentlyCreated) {
            $created['categories'] = 1;
        }

        $unit = trim((string) ($row['unidad'] ?? '')) ?: 'unidad';
        $quantity = $this->parseNumber($row['cantidad'] ?? null);
        $cost = $this->parseNumber($row['costo'] ?? null);
        $price = $this->parseNumber($row['precio'] ?? null);
        $expiry = InventoryDateParser::lastDayOfMonth($row['vencimiento'] ?? null);
        $notes = trim((string) ($row['observaciones'] ?? ''));

        $reviewReasons = [];
        if ($notes !== '') {
            $reviewReasons[] = 'observación: '.$notes;
        }
        if ($quantity === null) {
            $reviewReasons[] = 'cantidad vacía';
        }
        if ($this->missingSize($category, $split['presentation'])) {
            $reviewReasons[] = 'talla no escrita';
        }
        if ($this->doubtfulPrice($cost, $price)) {
            $reviewReasons[] = 'precio dudoso';
        }
        if ($categoryName === '') {
            $reviewReasons[] = 'información incompleta';
        }
        if ($expiry === null && ($row['vencimiento'] ?? '') !== '' && ($row['vencimiento'] ?? null) !== null) {
            $reviewReasons[] = 'fecha de vencimiento no reconocida';
        }

        $needsReview = $reviewReasons !== [];

        $producto = Producto::query()
            ->where('categoria_id', $category->id)
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($split['nombre'])])
            ->first();

        if (! $producto) {
            $producto = Producto::create([
                'nombre' => $split['nombre'],
                'descripcion' => $notes !== '' ? $notes : null,
                'categoria_id' => $category->id,
                'marca_id' => $marca->id,
                'proveedor_id' => null,
                'presentacion' => $split['presentation'] !== '' ? $split['presentation'] : null,
                'unidad_medida' => $unit,
                'requires_batch' => $expiry !== null || $this->isMedicine($category),
                'activo' => true,
                'precio_compra' => $cost ?? 0,
                'precio_venta' => $price ?? 0,
            ]);
            $created['products'] = 1;
        } else {
            $producto->fill([
                'requires_batch' => $producto->requires_batch || $expiry !== null,
            ])->save();
        }

        $variante = Variante::query()
            ->where('producto_id', $producto->id)
            ->whereRaw('LOWER(COALESCE(presentation, "")) = ?', [mb_strtolower($split['presentation'])])
            ->whereRaw('LOWER(unit) = ?', [mb_strtolower($unit)])
            ->first();

        $reviewNote = $needsReview ? implode('; ', $reviewReasons) : null;

        if (! $variante) {
            $variante = Variante::create([
                'producto_id' => $producto->id,
                'sku' => $this->uniqueSku($split['nombre'], $split['presentation']),
                'presentation' => $split['presentation'],
                'unit' => $unit,
                'sale_unit' => $unit,
                'cost_price' => $cost,
                'sale_price' => $price,
                'needs_review' => $needsReview,
                'review_note' => $reviewNote,
                'activo' => true,
            ]);
            $created['variants'] = 1;
        } else {
            $variante->fill([
                'cost_price' => $cost ?? $variante->cost_price,
                'sale_price' => $price ?? $variante->sale_price,
                'needs_review' => $variante->needs_review || $needsReview,
                'review_note' => trim(implode('; ', array_filter([$variante->review_note, $reviewNote]))),
            ])->save();
        }

        $qty = (int) ($quantity ?? 0);
        $batch = Batch::create([
            'variant_id' => $variante->id,
            'batch_code' => sprintf('IMP-%s-%d-%s', now()->format('Ymd'), $excelRow, Str::lower(Str::random(4))),
            'expiry_date' => $expiry,
            'quantity' => 0,
            'cost' => $cost,
        ]);
        $created['batches'] = 1;

        if ($qty > 0) {
            $this->stock->move(
                $batch,
                StockMovement::TYPE_INICIAL,
                $qty,
                $user,
                'Importación de inventario',
                'import:row:'.$excelRow
            );
            $created['movements'] = 1;
        }

        return [
            'needs_review' => (bool) $variante->needs_review,
            'created' => $created,
        ];
    }

    private function findOrCreateCategory(string $nombre, string $productName = '', string $presentation = ''): Categoria
    {
        if ($nombre === '') {
            return Categoria::query()->firstOrCreate(
                ['nombre' => 'Sin categoría'],
                ['descripcion' => 'Creada por importación', 'activo' => true]
            );
        }

        $resolved = CatalogCategoryResolver::resolve($nombre, $productName, $presentation);
        $query = Categoria::query()->whereRaw('LOWER(nombre) = ?', [mb_strtolower($resolved['nombre'])]);
        if ($resolved['parent']) {
            $query->whereHas('parent', fn ($parent) => $parent->whereRaw('LOWER(nombre) = ?', [mb_strtolower($resolved['parent'])]));
        }
        $existing = $query->first()
            ?? Categoria::query()->whereRaw('LOWER(nombre) = ?', [mb_strtolower($resolved['nombre'])])->first();

        if ($existing) {
            return $existing->loadMissing('parent');
        }

        return Categoria::create([
            'nombre' => $resolved['nombre'],
            'descripcion' => 'Creada por importación',
            'activo' => true,
        ]);
    }

    private function parseNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }

        $text = trim((string) $value);
        $text = preg_replace('/[^\d,.\-]/', '', $text) ?? $text;
        if ($text === '' || $text === '-') {
            return null;
        }
        if (str_contains($text, ',') && str_contains($text, '.')) {
            $text = str_replace(',', '', $text);
        } elseif (str_contains($text, ',')) {
            $text = str_replace(',', '.', $text);
        }

        return is_numeric($text) ? (float) $text : null;
    }

    private function doubtfulPrice(?float $cost, ?float $price): bool
    {
        if ($price === null || $price <= 0) {
            return true;
        }
        if ($cost !== null && $cost > 0 && $price > 0 && $cost > $price * 3) {
            return true;
        }

        return false;
    }

    private function missingSize(Categoria $category, string $presentation): bool
    {
        $haystack = mb_strtolower($category->nombre.' '.($category->parent?->nombre ?? ''));
        $needsSize = str_contains($haystack, 'ropa')
            || str_contains($haystack, 'accesorio')
            || str_contains($haystack, 'cama');

        if (! $needsSize) {
            return false;
        }

        return ! preg_match('/\b(talla|size|xs|s|m|l|xl|xxl|\d+\s*cm)\b/iu', $presentation);
    }

    private function isMedicine(Categoria $category): bool
    {
        $haystack = mb_strtolower($category->nombre.' '.($category->parent?->nombre ?? ''));

        return str_contains($haystack, 'medicament')
            || str_contains($haystack, 'farmacia')
            || str_contains($haystack, 'antiparasit')
            || str_contains($haystack, 'antibiot')
            || str_contains($haystack, 'vitamina');
    }

    private function uniqueSku(string $nombre, string $presentation): string
    {
        $base = strtoupper(Str::slug(mb_substr($nombre.'-'.$presentation, 0, 40)));
        $sku = $base !== '' ? $base : 'IMP';
        $candidate = $sku;
        $i = 1;
        while (Variante::query()->where('sku', $candidate)->exists()) {
            $candidate = $sku.'-'.$i;
            $i++;
        }

        return $candidate;
    }
}
