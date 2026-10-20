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

class CustomerV1Test extends TestCase
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

    public function test_customer_pet_sale_history_and_pos_search(): void
    {
        $suffix = str_replace('.', '', uniqid());

        $customer = $this->withToken($this->token)->postJson('/api/v1/customers', [
            'name' => 'Lucía Ramos '.$suffix,
            'document_type' => 'DNI',
            'document_number' => (string) random_int(10000000, 99999999),
            'phone' => '987654321',
        ])->assertCreated()->json('data');

        $this->assertSame('Lucía Ramos '.$suffix, $customer['name']);

        $found = $this->withToken($this->token)
            ->getJson('/api/v1/customers?q='.$suffix)
            ->assertOk()
            ->json('data.items');
        $this->assertTrue(collect($found)->contains(fn ($row) => $row['id'] === $customer['id']));

        $pet = $this->withToken($this->token)->postJson('/api/v1/customers/'.$customer['id'].'/pets', [
            'nombre' => 'Max',
            'especie' => 'canino',
            'raza' => 'mestizo',
            'sexo' => 'macho',
            'peso' => 12.5,
            'alergias' => 'ninguna',
        ])->assertCreated()->json('data');

        $variant = $this->makeVariant();
        $this->stock($variant, 3);
        $this->withToken($this->token)->postJson('/api/v1/cash-sessions/open', ['opening_amount' => 50]);

        $sale = $this->withToken($this->token)->postJson('/api/v1/sales', [
            'idempotency_key' => 'cust-'.$suffix,
            'customer_id' => $customer['id'],
            'pet_id' => $pet['id'],
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'payments' => [['method' => 'efectivo', 'amount' => 10]],
        ])->assertCreated()->json('data');

        $this->assertSame($customer['id'], $sale['customer_id']);
        $this->assertSame($pet['id'], $sale['pet_id']);

        $history = $this->withToken($this->token)
            ->getJson('/api/v1/customers/'.$customer['id'].'/purchases')
            ->assertOk()
            ->json('data.items');
        $this->assertTrue(collect($history)->contains(fn ($row) => $row['id'] === $sale['id']));

        $receipt = $this->withToken($this->token)
            ->getJson('/api/v1/sales/'.$sale['id'].'/receipt')
            ->json('data');
        $this->assertStringContainsString('Lucía', $receipt['cliente']);
        $this->assertSame('Max', $receipt['mascota']);
    }

    private function makeVariant(): Variante
    {
        $categoria = Categoria::query()->create(['nombre' => 'Cli '.uniqid(), 'activo' => true]);
        $producto = Producto::create([
            'nombre' => 'Prod cli '.uniqid(),
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        return Variante::create([
            'producto_id' => $producto->id,
            'sku' => 'CLI-'.uniqid(),
            'presentation' => 'u',
            'sale_price' => 10,
            'cost_price' => 3,
            'activo' => true,
        ]);
    }

    private function stock(Variante $variant, int $qty): void
    {
        $batch = Batch::create([
            'variant_id' => $variant->id,
            'batch_code' => 'C-'.uniqid(),
            'quantity' => 0,
        ]);
        $this->withToken($this->token)->postJson('/api/v1/stock/adjustments', [
            'batch_id' => $batch->id,
            'direction' => 'entrada',
            'quantity' => $qty,
            'reason' => 'test cliente',
        ])->assertCreated();
    }
}
