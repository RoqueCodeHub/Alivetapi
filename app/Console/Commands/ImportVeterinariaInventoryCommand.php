<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\InventoryImportService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportVeterinariaInventoryCommand extends Command
{
    protected $signature = 'inventory:import-xlsx {path?} {--keep : No vaciar productos antes de importar}';

    protected $description = 'Importa el inventario Excel de la veterinaria y lo registra por categoría oficial';

    public function handle(InventoryImportService $importer): int
    {
        $path = $this->argument('path')
            ?: 'D:\\DESCARGAS\\Inventario_veterinaria.xlsx';

        if (! is_file($path)) {
            $fallback = storage_path('app/imports/Inventario_veterinaria.xlsx');
            if (is_file($fallback)) {
                $path = $fallback;
            }
        }

        if (! is_file($path)) {
            $this->error('No se encontró el Excel: '.$path);

            return self::FAILURE;
        }

        $user = User::query()->where('email', 'admin@alivet.com')->first()
            ?? User::query()->orderBy('id')->first();
        if (! $user) {
            $this->error('No hay un usuario para registrar el movimiento de stock.');

            return self::FAILURE;
        }

        if (! $this->option('keep')) {
            $this->wipeProducts();
        }

        $file = new UploadedFile($path, basename($path), null, null, true);
        $summary = $importer->import($file, $user);
        app(\App\Services\StockService::class)->rebuildProductoStock();

        $this->info('Procesadas: '.$summary['processed']);
        $this->info('Importadas: '.$summary['imported']);
        $this->info('Rechazadas: '.$summary['rejected']);
        $this->info('Pendientes de revisión: '.$summary['pending_review']);
        $this->info('Productos creados: '.$summary['created']['products']);
        $this->info('Variantes: '.$summary['created']['variants']);
        $this->info('Lotes: '.$summary['created']['batches']);

        if ($summary['errors'] !== []) {
            $this->warn('Errores (máx. 20):');
            foreach (array_slice($summary['errors'], 0, 20) as $error) {
                $this->line('Fila '.$error['row'].': '.$error['message']);
            }
        }

        return $summary['rejected'] === $summary['processed'] ? self::FAILURE : self::SUCCESS;
    }

    private function wipeProducts(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ([
            'sale_return_items',
            'sale_returns',
            'sale_items',
            'clinical_consumptions',
            'stock_count_items',
            'purchase_items',
            'cost_histories',
            'stock_movements',
            'batches',
            'variantes',
            'venta_detalles',
            'movimientos_stock',
            'productos',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
