<?php

namespace App\Console\Commands;

use App\Services\StockService;
use Illuminate\Console\Command;

class SyncProductoStockCommand extends Command
{
    protected $signature = 'stock:sync-productos {producto_id?}';

    protected $description = 'Recalcula productos.stock_actual sumando las cantidades de los lotes';

    public function handle(StockService $stock): int
    {
        $productoId = $this->argument('producto_id');
        $updated = $stock->rebuildProductoStock($productoId ? (int) $productoId : null);
        $this->info("Stock actualizado en {$updated} productos a partir de los lotes.");

        return self::SUCCESS;
    }
}
