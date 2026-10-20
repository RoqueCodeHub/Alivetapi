<?php

namespace Tests\Feature\Api;

use App\Models\Batch;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use App\Models\Variante;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PurchaseV1Test extends TestCase
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

    public function test_confirmed_purchase_increases_batch_stock_and_cost_history(): void
    {
        $variant = $this->makeVariant();
        $supplier = $this->withToken($this->token)->postJson('/api/v1/suppliers', [
            'name' => 'Droguería '.$this->unique(),
            'ruc' => (string) random_int(20000000000, 20999999999),
            'phone' => '999111222',
            'email' => 'prov'.$this->unique().'@alivet.com',
        ])->assertCreated()->json('data');

        $purchase = $this->withToken($this->token)->postJson('/api/v1/purchases', [
            'supplier_id' => $supplier['id'],
            'comprobante' => 'F001-'.random_int(100, 999),
            'fecha' => now()->toDateString(),
            'items' => [[
                'variant_id' => $variant->id,
                'batch_code' => 'L-COMP-'.$this->unique(),
                'expiry_date' => now()->addYear()->toDateString(),
                'quantity' => 12,
                'cost' => 8.5,
            ]],
        ])->assertCreated()->json('data');

        $this->assertSame('draft', $purchase['status']);
        $this->assertEquals(102.0, (float) $purchase['total']);

        $confirmed = $this->withToken($this->token)
            ->postJson('/api/v1/purchases/'.$purchase['id'].'/confirm')
            ->assertOk()
            ->json('data');

        $this->assertSame('confirmed', $confirmed['status']);
        $batchId = $confirmed['items'][0]['batch_id'];
        $this->assertSame(12, Batch::find($batchId)->quantity);
        $this->assertEquals(8.5, (float) $variant->fresh()->cost_price);

        $kardex = $this->withToken($this->token)
            ->getJson('/api/v1/variants/'.$variant->id.'/kardex')
            ->json('data');
        $last = end($kardex);
        $this->assertSame('compra', $last['movimiento']);
        $this->assertSame(12, $last['saldo']);

        $history = $this->withToken($this->token)
            ->getJson('/api/v1/variants/'.$variant->id.'/cost-history')
            ->assertOk()
            ->json('data');
        $this->assertEquals(8.5, (float) $history[0]['cost']);

        $this->withToken($this->token)
            ->postJson('/api/v1/purchases/'.$purchase['id'].'/confirm')
            ->assertStatus(422);

        $this->withToken($this->token)->postJson('/api/v1/purchases/'.$purchase['id'].'/payments', [
            'amount' => 50,
            'method' => 'transferencia',
        ])->assertOk()->assertJsonPath('data.payment_status', 'parcial');

        $payables = $this->withToken($this->token)->getJson('/api/v1/purchases/payables')->assertOk()->json('data');
        $found = collect($payables['items'])->contains(fn ($row) => $row['id'] === $purchase['id']);
        if (! $found && ($payables['meta']['last_page'] ?? 1) > 1) {
            $found = collect($this->withToken($this->token)
                ->getJson('/api/v1/purchases/payables?page='.$payables['meta']['last_page'])
                ->json('data.items'))
                ->contains(fn ($row) => $row['id'] === $purchase['id']);
        }
        $this->assertTrue($found);
    }

    public function test_user_without_purchase_permission_cannot_create_supplier(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles(['Cliente']);

        $this->withToken($user->createToken('api')->plainTextToken)
            ->postJson('/api/v1/suppliers', ['name' => 'X'])
            ->assertForbidden();
    }

    private function makeVariant(): Variante
    {
        $categoria = Categoria::query()->create([
            'nombre' => 'Compra cat '.$this->unique(),
            'activo' => true,
        ]);
        $producto = Producto::create([
            'nombre' => 'Producto compra '.$this->unique(),
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        return Variante::create([
            'producto_id' => $producto->id,
            'sku' => 'C-'.$this->unique(),
            'presentation' => 'caja',
            'unit' => 'caja',
            'cost_price' => 1,
            'sale_price' => 10,
            'activo' => true,
        ]);
    }

    private function unique(): string
    {
        return str_replace('.', '', uniqid());
    }
}
