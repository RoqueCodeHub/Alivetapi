<?php

namespace Tests\Feature\Api;

use App\Models\Batch;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Sale;
use App\Models\SaleItem;
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
        $customer = $this->customer();

        $this->flushHeaders();
        $this->actingAs($operator, 'sanctum')
            ->postJson('/api/v1/cash-sessions/open', ['opening_amount' => 10])
            ->assertForbidden();
        $this->actingAs($operator, 'sanctum')->postJson('/api/v1/sales', [
            'idempotency_key' => 'op-'.$this->id(),
            'pending' => true,
            'customer_id' => $customer,
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
        ])->assertForbidden();

        $own = Sale::create([
            'user_id' => $operator->id,
            'series' => 'B001',
            'number' => ((int) Sale::query()->where('series', 'B001')->max('number')) + 1,
            'subtotal' => 10,
            'discount' => 0,
            'total' => 10,
            'change_amount' => 0,
            'payment_status' => Sale::PAYMENT_PAID,
            'status' => Sale::STATUS_COMPLETED,
            'idempotency_key' => 'own-'.$this->id(),
        ]);

        $list = collect($this->actingAs($operator, 'sanctum')->getJson('/api/v1/sales')->json('data.items'));
        $this->assertTrue($list->contains(fn ($row) => $row['id'] === $own->id));
        $this->assertFalse($list->contains(fn ($row) => $row['id'] === $adminSale['id']));

        $this->actingAs($operator, 'sanctum')
            ->postJson('/api/v1/sales/'.$own->id.'/void', ['reason' => 'no'])
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

    public function test_idempotency_key_does_not_reveal_another_cashiers_sale(): void
    {
        $variant = $this->makeVariant(10);
        $this->stockBatch($variant, 4, now()->addMonth()->toDateString());
        $this->withToken($this->token)->postJson('/api/v1/cash-sessions/open', ['opening_amount' => 0])->assertCreated();

        $key = 'shared-'.$this->id();
        $sale = $this->withToken($this->token)->postJson('/api/v1/sales', [
            'idempotency_key' => $key,
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'payments' => [['method' => 'efectivo', 'amount' => 10]],
        ])->assertCreated()->json('data');

        $cashier = User::factory()->create(['is_active' => true]);
        $cashier->syncRoles(['Cajero']);
        $cashierToken = $cashier->createToken('api')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        $this->withToken($cashierToken)->postJson('/api/v1/sales', [
            'idempotency_key' => $key,
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'payments' => [['method' => 'efectivo', 'amount' => 10]],
        ])->assertStatus(422)->assertJsonMissingPath('data.id');

        $this->assertSame($sale['id'], Sale::query()->where('idempotency_key', $key)->value('id'));
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

    public function test_credit_sale_moves_stock_and_accepts_partial_payments(): void
    {
        $variant = $this->makeVariant(12);
        $batch = $this->stockBatch($variant, 5, now()->addMonth()->toDateString());
        $customer = $this->customer();

        $cashier = User::factory()->create(['is_active' => true]);
        $cashier->syncRoles(['Cajero']);
        $cashierToken = $cashier->createToken('api')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->withToken($cashierToken)->postJson('/api/v1/sales', [
            'idempotency_key' => 'cajero-fiado-'.$this->id(),
            'pending' => true,
            'customer_id' => $customer,
            'items' => [['variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 12]],
        ])->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $draft = $this->withToken($this->token)->postJson('/api/v1/sales', [
            'idempotency_key' => 'fiado-'.$this->id(),
            'pending' => true,
            'customer_id' => $customer,
            'items' => [['variant_id' => $variant->id, 'quantity' => 2, 'unit_price' => 12]],
        ])->assertCreated()->json('data');

        $this->assertSame('pending', $draft['status']);
        $this->assertSame('pendiente', $draft['payment_status']);
        $this->assertEquals(24, (float) $draft['balance']);
        $this->assertSame(3, $batch->fresh()->quantity);

        $this->withToken($this->token)->postJson('/api/v1/sales/'.$draft['id'].'/returns', [
            'reason' => 'aún no está pagada',
            'items' => [['sale_item_id' => $draft['items'][0]['id'], 'quantity' => 1]],
        ])->assertStatus(422);

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->withToken($cashierToken)->postJson('/api/v1/cash-sessions/open', [
            'opening_amount' => 20,
        ])->assertCreated();

        $partial = $this->withToken($cashierToken)->postJson('/api/v1/sales/'.$draft['id'].'/pay', [
            'payments' => [['method' => 'efectivo', 'amount' => 10]],
        ])->assertOk()->json('data');
        $this->assertSame('pending', $partial['status']);
        $this->assertEquals(14, (float) $partial['balance']);
        $this->assertSame(3, $batch->fresh()->quantity);

        $session = $this->withToken($cashierToken)->getJson('/api/v1/cash-sessions/current')->json('data');
        $this->assertEquals(30, (float) $session['expected_amount']);

        $this->withToken($cashierToken)->postJson('/api/v1/sales/'.$draft['id'].'/void', [
            'reason' => 'ya hay abono',
        ])->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->withToken($this->token)->postJson('/api/v1/sales/'.$draft['id'].'/void', [
            'reason' => 'ya hay abono',
        ])->assertStatus(422);

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $paid = $this->withToken($cashierToken)->postJson('/api/v1/sales/'.$draft['id'].'/pay', [
            'payments' => [['method' => 'yape', 'amount' => 14]],
        ])->assertOk()->json('data');
        $this->assertSame('completed', $paid['status']);
        $this->assertEquals(0, (float) $paid['balance']);
        $this->assertSame(3, $batch->fresh()->quantity);

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $receipt = $this->withToken($this->token)->getJson('/api/v1/sales/'.$draft['id'].'/receipt')->json('data');
        $this->assertEquals(0, (float) $receipt['balance']);
        $this->assertEquals(24, (float) $receipt['paid']);
    }

    public function test_unpaid_credit_can_be_voided_and_legacy_debt_stocks_on_payment(): void
    {
        $variant = $this->makeVariant(8);
        $batch = $this->stockBatch($variant, 4, now()->addMonth()->toDateString());
        $customer = $this->customer();

        $credit = $this->withToken($this->token)->postJson('/api/v1/sales', [
            'idempotency_key' => 'anula-'.$this->id(),
            'pending' => true,
            'customer_id' => $customer,
            'items' => [['variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 8]],
        ])->assertCreated()->json('data');
        $this->assertSame(3, $batch->fresh()->quantity);

        $this->withToken($this->token)->postJson('/api/v1/sales/'.$credit['id'].'/void', [
            'reason' => 'el cliente desistió',
        ])->assertOk();
        $this->assertSame(4, $batch->fresh()->quantity);

        $legacy = Sale::create([
            'user_id' => $this->admin->id,
            'customer_id' => $customer,
            'series' => 'B001',
            'number' => ((int) Sale::query()->where('series', 'B001')->max('number')) + 1,
            'subtotal' => 8,
            'discount' => 0,
            'total' => 8,
            'change_amount' => 0,
            'payment_status' => Sale::PAYMENT_PENDING,
            'status' => Sale::STATUS_PENDING,
            'idempotency_key' => 'legacy-'.$this->id(),
        ]);
        $legacy->items()->create([
            'item_type' => SaleItem::TYPE_PRODUCT,
            'variant_id' => $variant->id,
            'description' => 'Deuda anterior',
            'quantity' => 1,
            'unit_price' => 8,
            'unit_cost' => 4,
            'discount' => 0,
            'line_total' => 8,
        ]);

        $this->withToken($this->token)->postJson('/api/v1/cash-sessions/open', ['opening_amount' => 0]);
        $this->withToken($this->token)->postJson('/api/v1/sales/'.$legacy->id.'/pay', [
            'payments' => [['method' => 'efectivo', 'amount' => 8]],
        ])->assertOk();
        $this->assertSame(3, $batch->fresh()->quantity);
    }

    public function test_legacy_register_is_not_available(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Intruso',
            'email' => 'intruso-'.$this->id().'@example.com',
            'password' => 'secret123',
            'role_id' => 1,
        ])->assertNotFound();
    }

    private function customer(): int
    {
        return (int) $this->withToken($this->token)->postJson('/api/v1/customers', [
            'name' => 'Cliente fiado '.$this->id(),
            'document_type' => 'DNI',
            'document_number' => (string) random_int(10000000, 99999999),
        ])->assertCreated()->json('data.id');
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
