<?php

namespace Tests\Feature\Api;

use App\Models\Batch;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Sale;
use App\Models\User;
use App\Models\Variante;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PosV1Test extends TestCase
{
    private string $token;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sanctum.stateful' => []]);
        $this->seed(PermissionSeeder::class);

        $this->admin = User::factory()->create([
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->admin->syncRoles(['Administrador']);
        $this->token = $this->admin->createToken('api')->plainTextToken;
    }

    public function test_sale_uses_fefo_idempotency_change_and_void_restocks(): void
    {
        $variant = $this->makeVariant(15.0);
        $late = $this->stockBatch($variant, 8, now()->addMonths(6)->toDateString());
        $early = $this->stockBatch($variant, 5, now()->addDays(8)->toDateString());

        $this->withToken($this->token)->postJson('/api/v1/cash-sessions/open', [
            'opening_amount' => 100,
        ])->assertCreated();

        $payload = [
            'idempotency_key' => 'key-'.$this->id(),
            'discount' => 0,
            'items' => [[
                'variant_id' => $variant->id,
                'quantity' => 6,
            ]],
            'payments' => [
                ['method' => 'efectivo', 'amount' => 50],
                ['method' => 'yape', 'amount' => 50],
            ],
        ];

        $sale = $this->withToken($this->token)
            ->postJson('/api/v1/sales', $payload)
            ->assertCreated()
            ->json('data');

        $this->assertEquals(90, (float) $sale['total']);
        $this->assertEquals(10, (float) $sale['change_amount']);
        $this->assertSame(0, $early->fresh()->quantity);
        $this->assertSame(7, $late->fresh()->quantity);

        $this->withToken($this->token)
            ->postJson('/api/v1/sales', $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $sale['id']);

        $this->withToken($this->token)
            ->getJson('/api/v1/sales/'.$sale['id'].'/receipt')
            ->assertOk()
            ->assertJsonPath('data.document', $sale['document']);

        $this->withToken($this->token)->postJson('/api/v1/sales/'.$sale['id'].'/void', [
            'reason' => 'Error de cobro',
        ])->assertOk();

        $this->assertSame(5, $early->fresh()->quantity);
        $this->assertSame(8, $late->fresh()->quantity);
        $this->assertSame(Sale::STATUS_VOIDED, Sale::find($sale['id'])->status);
    }

    public function test_operator_sees_own_sales_and_cannot_void(): void
    {
        $variant = $this->makeVariant(10);
        $this->stockBatch($variant, 4, now()->addMonth()->toDateString());

        $this->withToken($this->token)->postJson('/api/v1/cash-sessions/open', ['opening_amount' => 20]);
        $adminSale = $this->withToken($this->token)->postJson('/api/v1/sales', [
            'idempotency_key' => 'admin-'.$this->id(),
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'payments' => [['method' => 'efectivo', 'amount' => 10]],
        ])->json('data');

        $operator = User::factory()->create(['is_active' => true]);
        $operator->syncRoles(['Operador de tienda']);

        $this->flushHeaders();
        $this->actingAs($operator, 'sanctum')
            ->postJson('/api/v1/cash-sessions/open', ['opening_amount' => 10])
            ->assertCreated();
        $opSale = $this->actingAs($operator, 'sanctum')->postJson('/api/v1/sales', [
            'idempotency_key' => 'op-'.$this->id(),
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'payments' => [['method' => 'efectivo', 'amount' => 10]],
        ])->assertCreated()->json('data');

        $list = collect($this->actingAs($operator, 'sanctum')->getJson('/api/v1/sales')->json('data.items'));
        $this->assertTrue($list->contains(fn ($row) => $row['id'] === $opSale['id']));
        $this->assertFalse($list->contains(fn ($row) => $row['id'] === $adminSale['id']));

        $this->actingAs($operator, 'sanctum')
            ->postJson('/api/v1/sales/'.$opSale['id'].'/void', ['reason' => 'no'])
            ->assertForbidden();
    }

    public function test_service_sale_return_and_cash_close_difference(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/cash-sessions/open', [
            'opening_amount' => 100,
        ]);

        $sale = $this->withToken($this->token)->postJson('/api/v1/sales', [
            'idempotency_key' => 'svc-'.$this->id(),
            'items' => [[
                'item_type' => 'service',
                'description' => 'Consulta',
                'quantity' => 1,
                'unit_price' => 40,
            ]],
            'payments' => [['method' => 'efectivo', 'amount' => 40]],
        ])->assertCreated()->json('data');

        $this->withToken($this->token)->postJson('/api/v1/sales/'.$sale['id'].'/returns', [
            'reason' => 'Cliente desistió',
            'refund_method' => 'efectivo',
            'items' => [[
                'sale_item_id' => $sale['items'][0]['id'],
                'quantity' => 1,
            ]],
        ])->assertCreated();

        $current = $this->withToken($this->token)->getJson('/api/v1/cash-sessions/current')->json('data');
        $closed = $this->withToken($this->token)->postJson('/api/v1/cash-sessions/'.$current['id'].'/close', [
            'counted_amount' => 99,
        ])->assertOk()->json('data');

        $this->assertEquals(100, (float) $closed['expected_amount']);
        $this->assertEquals(-1, (float) $closed['difference']);
    }

    public function test_pos_catalog_returns_one_card_per_variant(): void
    {
        $categoria = Categoria::query()->create(['nombre' => 'POS Cat '.$this->id(), 'activo' => true]);
        $producto = Producto::create([
            'nombre' => 'Antiparasitario Catalog '.$this->id(),
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);
        $a = Variante::create([
            'producto_id' => $producto->id,
            'sku' => 'A-'.$this->id(),
            'presentation' => 'Frasco 100 ml',
            'unit' => 'unidad',
            'sale_price' => 30,
            'activo' => true,
        ]);
        $b = Variante::create([
            'producto_id' => $producto->id,
            'sku' => 'B-'.$this->id(),
            'presentation' => 'Frasco 250 ml',
            'unit' => 'unidad',
            'sale_price' => 48,
            'activo' => true,
        ]);
        $this->stockBatch($a, 5, now()->addMonth()->toDateString());
        $this->stockBatch($b, 2, now()->addMonths(2)->toDateString());

        $items = collect($this->withToken($this->token)
            ->getJson('/api/v1/pos/search?q='.urlencode($producto->nombre).'&per_page=48')
            ->assertOk()
            ->json('data.items'));

        $this->assertCount(2, $items);
        $this->assertTrue($items->contains(fn ($row) => $row['variant_id'] === $a->id && (int) $row['on_hand'] === 5));
        $this->assertTrue($items->contains(fn ($row) => $row['variant_id'] === $b->id && (int) $row['on_hand'] === 2));
    }

    public function test_insufficient_stock_is_rejected(): void
    {
        $variant = $this->makeVariant(10);
        $this->stockBatch($variant, 1, now()->addMonth()->toDateString());
        $this->withToken($this->token)->postJson('/api/v1/cash-sessions/open', ['opening_amount' => 0]);

        $this->withToken($this->token)->postJson('/api/v1/sales', [
            'idempotency_key' => 'fail-'.$this->id(),
            'items' => [['variant_id' => $variant->id, 'quantity' => 5]],
            'payments' => [['method' => 'efectivo', 'amount' => 50]],
        ])->assertStatus(422);
    }

    public function test_pending_sale_waits_for_payment_before_stock_out(): void
    {
        $variant = $this->makeVariant(12);
        $batch = $this->stockBatch($variant, 3, now()->addMonth()->toDateString());

        $draft = $this->withToken($this->token)->postJson('/api/v1/sales', [
            'idempotency_key' => 'pend-'.$this->id(),
            'pending' => true,
            'items' => [['variant_id' => $variant->id, 'quantity' => 2, 'unit_price' => 12]],
        ])->assertCreated()->json('data');

        $this->assertSame('pending', $draft['status']);
        $this->assertSame('pendiente', $draft['payment_status']);
        $this->assertSame(3, $batch->fresh()->quantity);

        $this->withToken($this->token)->postJson('/api/v1/cash-sessions/open', ['opening_amount' => 50]);

        $paid = $this->withToken($this->token)->postJson('/api/v1/sales/'.$draft['id'].'/pay', [
            'payments' => [['method' => 'efectivo', 'amount' => 24]],
        ])->assertOk()->json('data');

        $this->assertSame('completed', $paid['status']);
        $this->assertSame('pagado', $paid['payment_status']);
        $this->assertSame(1, $batch->fresh()->quantity);
    }

    private function makeVariant(float $price): Variante
    {
        $categoria = Categoria::query()->create(['nombre' => 'POS '.$this->id(), 'activo' => true]);
        $producto = Producto::create([
            'nombre' => 'Prod POS '.$this->id(),
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        return Variante::create([
            'producto_id' => $producto->id,
            'sku' => 'POS-'.$this->id(),
            'presentation' => 'unidad',
            'unit' => 'unidad',
            'sale_price' => $price,
            'cost_price' => 4,
            'activo' => true,
        ]);
    }

    private function stockBatch(Variante $variant, int $qty, string $expiry): Batch
    {
        $batch = Batch::create([
            'variant_id' => $variant->id,
            'batch_code' => 'L-'.$this->id(),
            'expiry_date' => $expiry,
            'quantity' => 0,
            'cost' => 4,
        ]);
        $this->withToken($this->token)->postJson('/api/v1/stock/adjustments', [
            'batch_id' => $batch->id,
            'direction' => 'entrada',
            'quantity' => $qty,
            'reason' => 'carga pos test',
        ])->assertCreated();

        return $batch->fresh();
    }

    private function id(): string
    {
        return str_replace('.', '', uniqid('', true));
    }
}
