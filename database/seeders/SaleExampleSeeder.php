<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\User;
use App\Models\Variante;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SaleExampleSeeder extends Seeder
{
    public function run(): void
    {
        $this->wipeSalesGraph();
        $this->insertExample();
    }

    private function wipeSalesGraph(): void
    {
        if (Schema::hasTable('appointments') && Schema::hasColumn('appointments', 'sale_id')) {
            DB::table('appointments')->update(['sale_id' => null]);
        }

        foreach ([
            'invoices',
            'sale_return_items',
            'sale_returns',
            'sale_payments',
            'sale_items',
            'sales',
            'cash_sessions',
            'daily_sales_summaries',
            'venta_detalles',
            'ventas',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        if (Schema::hasTable('stock_movements')) {
            DB::table('stock_movements')->where('type', 'venta')->delete();
            DB::table('stock_movements')->where('reference', 'like', 'sale:%')->delete();
        }

        if (Schema::hasTable('invoice_series')) {
            DB::table('invoice_series')->update(['next_number' => 1]);
        }

        foreach (['invoices', 'sale_return_items', 'sale_returns', 'sale_payments', 'sale_items', 'sales', 'cash_sessions'] as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
            }
        }
    }

    private function insertExample(): void
    {
        $user = User::query()->where('email', 'operador@alivet.com')->first()
            ?? User::query()->orderBy('id')->first();
        $variant = Variante::query()->with('producto')->orderBy('id')->first();
        $customer = Cliente::query()->orderBy('id')->first();

        if (! $user || ! $variant) {
            throw new \RuntimeException('Se necesita un usuario y un producto para la venta de ejemplo.');
        }

        $now = now();
        $qty = 1;
        $price = 10.00;
        $total = $price * $qty;
        $description = trim(($variant->producto?->nombre ?? 'Producto').' '.($variant->presentation ?? ''));

        $sessionId = DB::table('cash_sessions')->insertGetId([
            'user_id' => $user->id,
            'opened_at' => $now->copy()->subHour(),
            'closed_at' => $now,
            'opening_amount' => 100,
            'expected_amount' => 100 + $total,
            'counted_amount' => 100 + $total,
            'difference' => 0,
            'close_notes' => 'Caja de ejemplo',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $saleId = DB::table('sales')->insertGetId([
            'cash_session_id' => $sessionId,
            'user_id' => $user->id,
            'customer_id' => $customer?->id,
            'pet_id' => null,
            'series' => 'B001',
            'number' => 1,
            'subtotal' => $total,
            'discount' => 0,
            'total' => $total,
            'change_amount' => 0,
            'payment_status' => 'pagado',
            'status' => 'completed',
            'idempotency_key' => 'venta-ejemplo-'.Str::uuid(),
            'notes' => 'Venta de ejemplo',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('sale_items')->insert([
            'sale_id' => $saleId,
            'item_type' => 'product',
            'variant_id' => $variant->id,
            'batch_id' => $variant->batches()->orderBy('id')->value('id'),
            'description' => $description !== '' ? $description : 'Ítem de ejemplo',
            'quantity' => $qty,
            'unit_price' => $price,
            'unit_cost' => $variant->cost_price ?? $variant->producto?->precio_compra ?? 0,
            'discount' => 0,
            'line_total' => $total,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('sale_payments')->insert([
            'sale_id' => $saleId,
            'method' => 'efectivo',
            'amount' => $total,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
