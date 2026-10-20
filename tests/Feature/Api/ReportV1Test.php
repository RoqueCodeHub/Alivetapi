<?php

namespace Tests\Feature\Api;

use App\Models\Batch;
use App\Models\Categoria;
use App\Models\DailySalesSummary;
use App\Models\Producto;
use App\Models\User;
use App\Models\Variante;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportV1Test extends TestCase
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

    public function test_admin_reports_top_products_filters_and_exports(): void
    {
        $categoria = Categoria::query()->create(['nombre' => 'Rep '.$this->id(), 'activo' => true]);
        $variant = $this->makeVariant($categoria, 20, 'Prod A');
        $otherCat = Categoria::query()->create(['nombre' => 'Otra '.$this->id(), 'activo' => true]);
        $other = $this->makeVariant($otherCat, 10, 'Prod B');
        $this->stock($variant, 5);
        $this->stock($other, 5);

        $this->withToken($this->token)->postJson('/api/v1/cash-sessions/open', ['opening_amount' => 50]);
        $this->withToken($this->token)->postJson('/api/v1/sales', [
            'idempotency_key' => 'r1-'.$this->id(),
            'items' => [['variant_id' => $variant->id, 'quantity' => 3]],
            'payments' => [['method' => 'efectivo', 'amount' => 60]],
        ])->assertCreated();
        $this->withToken($this->token)->postJson('/api/v1/sales', [
            'idempotency_key' => 'r2-'.$this->id(),
            'items' => [['variant_id' => $other->id, 'quantity' => 1]],
            'payments' => [['method' => 'yape', 'amount' => 10]],
        ])->assertCreated();

        $this->assertNotNull(DailySalesSummary::query()->whereDate('summary_date', now()->toDateString())->first());

        $dash = $this->withToken($this->token)->getJson('/api/v1/reports/dashboard')->assertOk()->json('data');
        $this->assertSame('admin', $dash['role']);
        $this->assertGreaterThanOrEqual(70, (float) $dash['today']['total']);
        $this->assertGreaterThan(0, (float) $dash['profit']['profit']);
        $this->assertGreaterThan(0, (float) $dash['inventory_value']['value']);

        $top = $this->withToken($this->token)->getJson('/api/v1/reports/top-products')->assertOk()->json('data');
        $this->assertTrue(collect($top)->contains(fn ($row) => (int) $row['variant_id'] === $variant->id && (int) $row['quantity'] >= 3));

        $filtered = $this->withToken($this->token)
            ->getJson('/api/v1/reports/top-products?category_id='.$categoria->id)
            ->assertOk()
            ->json('data');
        $this->assertTrue(collect($filtered)->every(fn ($row) => (int) $row['variant_id'] === $variant->id));

        $byCat = $this->withToken($this->token)->getJson('/api/v1/reports/by-category')->assertOk()->json('data');
        $this->assertTrue(collect($byCat)->contains(fn ($row) => str_contains($row['categoria'], 'Rep')));

        $csv = $this->withToken($this->token)
            ->get('/api/v1/reports/export?report=top-products&format=csv')
            ->assertOk();
        $this->assertStringContainsString('cantidad', $csv->streamedContent() ?: $csv->getContent());

        $xlsx = $this->withToken($this->token)
            ->get('/api/v1/reports/export?report=sales-by-day&format=xlsx')
            ->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $xlsx->headers->get('content-type')
        );

        $pdf = $this->withToken($this->token)
            ->get('/api/v1/reports/export?report=profit&format=pdf')
            ->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_operator_sees_own_dashboard_not_admin_reports(): void
    {
        $categoria = Categoria::query()->create(['nombre' => 'Op '.$this->id(), 'activo' => true]);
        $variant = $this->makeVariant($categoria, 10, 'Op prod');
        $this->stock($variant, 2);
        $this->withToken($this->token)->postJson('/api/v1/cash-sessions/open', ['opening_amount' => 10]);
        $this->withToken($this->token)->postJson('/api/v1/sales', [
            'idempotency_key' => 'adm-'.$this->id(),
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'payments' => [['method' => 'efectivo', 'amount' => 10]],
        ]);

        $operator = User::factory()->create(['is_active' => true]);
        $operator->syncRoles(['Operador de tienda']);

        $this->flushHeaders();
        $dash = $this->actingAs($operator, 'sanctum')
            ->getJson('/api/v1/reports/dashboard')
            ->assertOk()
            ->json('data');
        $this->assertSame('operator', $dash['role']);
        $this->assertEquals(0, (float) $dash['today']['total']);

        $this->actingAs($operator, 'sanctum')
            ->getJson('/api/v1/reports/top-products')
            ->assertForbidden();
    }

    private function makeVariant(Categoria $categoria, float $price, string $name): Variante
    {
        $producto = Producto::create([
            'nombre' => $name.' '.$this->id(),
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        return Variante::create([
            'producto_id' => $producto->id,
            'sku' => 'R-'.$this->id(),
            'presentation' => 'u',
            'sale_price' => $price,
            'cost_price' => 4,
            'activo' => true,
        ]);
    }

    private function stock(Variante $variant, int $qty): void
    {
        $batch = Batch::create([
            'variant_id' => $variant->id,
            'batch_code' => 'RB-'.$this->id(),
            'quantity' => 0,
            'cost' => 4,
        ]);
        $this->withToken($this->token)->postJson('/api/v1/stock/adjustments', [
            'batch_id' => $batch->id,
            'direction' => 'entrada',
            'quantity' => $qty,
            'reason' => 'test reportes',
        ])->assertCreated();
    }

    private function id(): string
    {
        return str_replace('.', '', uniqid('', true));
    }
}
