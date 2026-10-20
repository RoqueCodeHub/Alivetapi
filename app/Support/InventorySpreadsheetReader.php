<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use ZipArchive;

class InventorySpreadsheetReader
{
    public const HEADERS = [
        'categoria' => ['categoria', 'categoría', 'category', 'categorias'],
        'producto' => ['producto', 'product', 'nombre', 'nombre_producto'],
        'presentacion' => ['presentacion', 'presentación', 'presentation', 'variante', 'presentaciontalla', 'tallapresentacion'],
        'cantidad' => ['cantidad', 'quantity', 'qty', 'stock'],
        'unidad' => ['unidad', 'unit', 'unidad_medida'],
        'vencimiento' => ['fv', 'fvcto', 'fechavcto', 'vencimiento', 'fechavencimiento', 'expiry', 'caducidad'],
        'costo' => ['costo', 'cost', 'costounitario', 'preciocompra'],
        'precio' => ['precio', 'price', 'precioventa', 'pvp', 'preciounitario'],
        'observaciones' => ['observaciones', 'observacion', 'observación', 'obs', 'notas', 'nota'],
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function read(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        $path = $file->getRealPath();

        if (in_array($extension, ['csv', 'txt'], true)) {
            return $this->fromMatrix($this->readCsv($path));
        }

        if (in_array($extension, ['xlsx', 'xlsm'], true)) {
            return $this->fromMatrix($this->readXlsx($path));
        }

        throw new \InvalidArgumentException('El archivo debe ser Excel (.xlsx) o CSV.');
    }

    /**
     * @param list<list<mixed>> $matrix
     * @return list<array<string, mixed>>
     */
    public function fromMatrix(array $matrix): array
    {
        $rows = [];
        $map = null;

        foreach ($matrix as $line) {
            $cells = array_map(fn ($cell) => is_string($cell) ? trim($cell) : $cell, $line);
            $nonEmpty = array_filter($cells, fn ($cell) => $cell !== null && $cell !== '');
            if ($nonEmpty === []) {
                continue;
            }

            if ($map === null) {
                $map = $this->headerMap($cells);
                continue;
            }

            $row = [];
            foreach ($map as $field => $index) {
                $row[$field] = $cells[$index] ?? null;
            }
            $rows[] = $row;
        }

        $lastCategory = '';
        foreach ($rows as $i => $row) {
            $category = trim((string) ($row['categoria'] ?? ''));
            $key = mb_strtolower($category);
            if ($category === '' || str_contains($key, 'sin encabezado') || str_contains($key, 'continuacion') || str_contains($key, 'continuación')) {
                $rows[$i]['categoria'] = $lastCategory;
            } else {
                $lastCategory = $category;
            }
        }

        return $rows;
    }

    /**
     * @param list<mixed> $cells
     * @return array<string, int>
     */
    private function headerMap(array $cells): array
    {
        $map = [];
        foreach ($cells as $index => $cell) {
            $key = $this->normalizeHeader($cell);
            foreach (self::HEADERS as $field => $aliases) {
                if (in_array($key, $aliases, true)) {
                    $map[$field] = $index;
                }
            }
        }

        if (! isset($map['producto'])) {
            throw new \InvalidArgumentException('La plantilla debe incluir la columna producto.');
        }

        return $map;
    }

    private function normalizeHeader(mixed $value): string
    {
        $text = mb_strtolower(trim((string) $value));
        $text = Str::ascii($text);
        $text = preg_replace('/[^a-z0-9]+/', '', $text) ?? $text;

        return $text;
    }

    /**
     * @return list<list<mixed>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo leer el CSV.');
        }

        $first = fgets($handle);
        if ($first === false) {
            fclose($handle);

            return [];
        }
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first) ?? $first;
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        rewind($handle);

        $matrix = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $matrix[] = $data;
        }
        fclose($handle);

        if ($matrix !== []) {
            $matrix[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $matrix[0][0]);
        }

        return $matrix;
    }

    /**
     * @return list<list<mixed>>
     */
    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('No se pudo abrir el Excel.');
        }

        $shared = $this->sharedStrings($zip);
        $sheets = $this->sheetPaths($zip);
        $matrix = [];

        foreach (array_slice($sheets, 0, 1) as $sheetPath) {
            $xml = $zip->getFromName($sheetPath);
            if ($xml === false) {
                continue;
            }
            $sheet = simplexml_load_string($xml);
            if ($sheet === false) {
                continue;
            }

            foreach ($sheet->sheetData->row as $row) {
                $cells = [];
                foreach ($row->c as $cell) {
                    $ref = (string) $cell['r'];
                    $col = $this->columnIndex(preg_replace('/\d+/', '', $ref) ?: 'A');
                    $cells[$col] = $this->cellValue($cell, $shared);
                }
                if ($cells === []) {
                    continue;
                }
                ksort($cells);
                $line = [];
                $max = max(array_keys($cells));
                for ($i = 0; $i <= $max; $i++) {
                    $line[] = $cells[$i] ?? '';
                }
                $matrix[] = $line;
            }
        }

        $zip->close();

        return $matrix;
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $doc = simplexml_load_string($xml);
        if ($doc === false) {
            return [];
        }

        $shared = [];
        foreach ($doc->si as $si) {
            $text = '';
            if (isset($si->t)) {
                $text = (string) $si->t;
            } else {
                foreach ($si->r as $run) {
                    $text .= (string) $run->t;
                }
            }
            $shared[] = $text;
        }

        return $shared;
    }

    /**
     * @return list<string>
     */
    private function sheetPaths(ZipArchive $zip): array
    {
        $paths = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (is_string($name) && preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                $paths[] = $name;
            }
        }
        natsort($paths);

        return array_values($paths);
    }

    private function cellValue(\SimpleXMLElement $cell, array $shared): mixed
    {
        $type = (string) $cell['t'];
        $raw = isset($cell->v) ? (string) $cell->v : '';

        if ($type === 's') {
            return $shared[(int) $raw] ?? '';
        }
        if ($type === 'inlineStr') {
            return (string) ($cell->is->t ?? '');
        }
        if ($raw !== '' && is_numeric($raw) && ! str_contains($raw, '.')) {
            return (int) $raw == $raw ? (int) $raw : (float) $raw;
        }
        if ($raw !== '' && is_numeric($raw)) {
            return (float) $raw;
        }

        return $raw;
    }

    private function columnIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $index = 0;
        $length = strlen($letters);
        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }
}
