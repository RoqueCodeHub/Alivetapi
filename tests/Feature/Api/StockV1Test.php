<?php

namespace Tests\Feature\Api;

use App\Models\Batch;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\StockCount;
use App\Models\User;
use App\Models\Variante;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StockV1Test extends TestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sanctum.stateful' => []]);
        $this->seed(PermissionSeeder::class);

        $admin = User::factory()->create([
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $admin->syncRoles(['Administrador']);
        $this->token = $admin->createToken('api')->plainTextToken;
    }

    public function test_stock_receipt_adds_batch_and_updates_product_stock(): void
    {
        $variant = $this->makeVariant();
        $producto = $variant->producto;

        $this->withToken($this->token)->postJson('/api/v1/stock/receipts', [
            'producto_id' => $producto->id,
            'variant_id' => $variant->id,
            'batch_code' => 'LOT-TEST-'.$producto->id,
            'quantity' => 12,
            'cost' => 8.5,
            'sale_price' => 22,
            'expiry_date' => now()->addMonths(4)->toDateString(),
        ])->assertCreated()->assertJsonPath('data.on_hand', 12);

        $this->assertSame(12, (int) $producto->fresh()->stock_actual);
        $this->assertEquals(22, (float) $variant->fresh()->sale_price);
        $this->assertDatabaseHas('batches', [
            'variant_id' => $variant->id,
            'batch_code' => 'LOT-TEST-'.$producto->id,
            'quantity' => 12,
        ]);
    }

    public function test_adjustment_appears_in_kardex_and_rejects_negative_stock(): void
    {
        $batch = $this->makeBatch(10);

        $this->withToken($this->token)->postJson('/api/v1/stock/adjustments', [
            'batch_id' => $batch->id,
            'direction' => 'salida',
            'quantity' => 4,
            'reason' => 'Merma de muestra',
        ])->assertCreated();

        $kardex = $this->withToken($this->token)
            ->getJson('/api/v1/variants/'.$batch->variant_id.'/kardex')
            ->assertOk()
            ->json('data');

        $this->assertSame(6, end($kardex)['saldo']);
        $this->assertSame(6, $batch->fresh()->quantity);

        $this->withToken($this->token)->postJson('/api/v1/stock/adjustments', [
            'batch_id' => $batch->id,
            'direction' => 'salida',
            'quantity' => 50,
            'reason' => 'Demasiado',
        ])->assertStatus(422);
    }

    public function test_fefo_picks_earliest_expiry(): void
    {
        $variant = $this->makeVariant();
        $late = Batch::create([
            'variant_id' => $variant->id,
            'batch_code' => 'LATE-'.uniqid(),
            'expiry_date' => now()->addMonths(8)->toDateString(),
            'quantity' => 0,
        ]);
        $early = Batch::create([
            'variant_id' => $variant->id,
            'batch_code' => 'EARLY-'.uniqid(),
            'expiry_date' => now()->addDays(10)->toDateString(),
            'quantity' => 0,
        ]);

        $this->withToken($this->token)->postJson('/api/v1/stock/adjustments', [
            'batch_id' => $late->id,
            'direction' => 'entrada',
            'quantity' => 8,
            'reason' => 'carga',
        ]);
        $this->withToken($this->token)->postJson('/api/v1/stock/adjustments', [
            'batch_id' => $early->id,
            'direction' => 'entrada',
            'quantity' => 5,
            'reason' => 'carga',
        ]);

        $fefo = $this->withToken($this->token)
            ->getJson('/api/v1/variants/'.$variant->id.'/fefo?quantity=6')
            ->assertOk()
            ->json('data');

        $this->assertSame($early->id, $fefo[0]['batch_id']);
        $this->assertSame(5, $fefo[0]['quantity']);
        $this->assertSame($late->id, $fefo[1]['batch_id']);
        $this->assertSame(1, $fefo[1]['quantity']);
    }

    public function test_alerts_low_stock_and_expiring(): void
    {
        $batch = $this->makeBatch(2, [
            'min_stock' => 5,
            'expiry_date' => now()->addDays(12)->toDateString(),
        ]);

        $low = $this->withToken($this->token)->getJson('/api/v1/alerts/low-stock')->assertOk()->json('data');
        $this->assertTrue(collect($low)->contains(fn ($row) => $row['variant_id'] === $batch->variant_id));

        $exp = $this->withToken($this->token)->getJson('/api/v1/alerts/expiring')->assertOk()->json('data');
        $this->assertTrue(collect($exp['vence_30'])->contains(fn ($row) => $row['batch_id'] === $batch->id));
    }

    public function test_physical_count_generates_adjustment(): void
    {
        $batch = $this->makeBatch(10);

        $count = $this->withToken($this->token)
            ->postJson('/api/v1/stock/counts', ['notes' => 'Cierre de mes'])
            ->assertCreated()
            ->json('data');

        $line = collect($count['items'])->firstWhere('batch_id', $batch->id);
        $this->assertNotNull($line);

        $payload = collect($count['items'])->map(fn ($item) => [
            'id' => $item['id'],
            'counted_qty' => $item['batch_id'] === $batch->id ? 7 : $item['system_qty'],
        ])->all();

        $this->withToken($this->token)->putJson('/api/v1/stock/counts/'.$count['id'].'/lines', [
            'items' => $payload,
        ])->assertOk();

        $this->withToken($this->token)
            ->postJson('/api/v1/stock/counts/'.$count['id'].'/approve')
            ->assertOk()
            ->assertJsonPath('data.status', StockCount::STATUS_APPROVED);

        $this->assertSame(7, $batch->fresh()->quantity);
    }

    public function test_open_box_converts_units(): void
    {
        $variant = $this->makeVariant(['units_per_box' => 42]);
        $batch = Batch::create([
            'variant_id' => $variant->id,
            'batch_code' => 'BOX-'.uniqid(),
            'quantity' => 0,
        ]);
        $this->withToken($this->token)->postJson('/api/v1/stock/adjustments', [
            'batch_id' => $batch->id,
            'direction' => 'entrada',
            'quantity' => 2,
            'reason' => 'compra cajas',
        ]);

        $this->withToken($this->token)->postJson('/api/v1/stock/open-box', [
            'batch_id' => $batch->id,
            'boxes' => 1,
        ])->assertOk()->assertJsonPath('data.units', 42);

        $this->assertSame(1, $batch->fresh()->quantity);
        $unpacked = Batch::query()->where('batch_code', $batch->batch_code.'-U')->first();
        $this->assertSame(42, $unpacked->quantity);
    }

    public function test_operator_cannot_create_count(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles(['Operador de tienda']);

        $this->withToken($user->createToken('api')->plainTextToken)
            ->postJson('/api/v1/stock/counts')
            ->assertForbidden();
    }

    public function test_stock_export_is_csv(): void
    {
        $this->makeBatch(3);

        $this->withToken($this->token)
            ->get('/api/v1/stock/export')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    private function makeBatch(int $qty, array $options = []): Batch
    {
        $variant = $this->makeVariant($options);
        $batch = Batch::create([
            'variant_id' => $variant->id,
            'batch_code' => 'L-'.uniqid(),
            'expiry_date' => $options['expiry_date'] ?? now()->addYear()->toDateString(),
            'quantity' => 0,
            'cost' => 1.5,
        ]);

        if ($qty > 0) {
            $this->withToken($this->token)->postJson('/api/v1/stock/adjustments', [
                'batch_id' => $batch->id,
                'direction' => 'entrada',
                'quantity' => $qty,
                'reason' => 'carga inicial test',
            ])->assertCreated();
        }

        return $batch->fresh();
    }

    private function makeVariant(array $options = []): Variante
    {
        $categoria = Categoria::query()->firstOrCreate(
            ['nombre' => 'Stock Test '.uniqid()],
            ['activo' => true]
        );
        $producto = Producto::create([
            'nombre' => 'Producto stock '.uniqid(),
            'categoria_id' => $categoria->id,
            'activo' => true,
            'requires_batch' => true,
        ]);

        return Variante::create([
            'producto_id' => $producto->id,
            'sku' => 'SKU-'.uniqid(),
            'presentation' => '100 ml',
            'unit' => 'caja',
            'sale_unit' => 'unidad',
            'units_per_box' => $options['units_per_box'] ?? 1,
            'min_stock' => $options['min_stock'] ?? 0,
            'sale_price' => 10,
            'activo' => true,
        ]);
    }
}
