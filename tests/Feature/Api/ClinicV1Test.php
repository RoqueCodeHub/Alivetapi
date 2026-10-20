<?php

namespace Tests\Feature\Api;

use App\Models\Batch;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use App\Models\Variante;
use App\Models\VetService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClinicV1Test extends TestCase
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

    public function test_consulta_vacuna_consumo_y_cobro(): void
    {
        $this->withToken($this->token)->getJson('/api/v1/services')->assertOk();
        $service = VetService::query()->where('code', 'consulta')->first();
        $this->assertNotNull($service);

        $customer = $this->withToken($this->token)->postJson('/api/v1/customers', [
            'name' => 'Ana Clinic '.$this->id(),
            'document_type' => 'DNI',
            'document_number' => (string) random_int(20000000, 29999999),
        ])->assertCreated()->json('data');

        $pet = $this->withToken($this->token)->postJson('/api/v1/customers/'.$customer['id'].'/pets', [
            'nombre' => 'Luna',
            'especie' => 'canino',
        ])->assertCreated()->json('data');

        $apt = $this->withToken($this->token)->postJson('/api/v1/appointments', [
            'pet_id' => $pet['id'],
            'clinic_service_id' => $service->id,
            'staff_user_id' => $this->admin->id,
            'scheduled_at' => now()->addHour()->toDateTimeString(),
        ])->assertCreated()->json('data');
        $this->assertSame('pendiente', $apt['status']);

        $record = $this->withToken($this->token)->postJson('/api/v1/appointments/'.$apt['id'].'/attend', [
            'diagnostico' => 'Control anual',
            'tratamiento' => 'Vacuna antirrábica',
            'peso' => 8.2,
        ])->assertOk()->json('data');
        $this->assertSame('Control anual', $record['diagnostico']);

        $vax = $this->withToken($this->token)->postJson('/api/v1/pets/'.$pet['id'].'/vaccinations', [
            'vaccine_name' => 'Antirrábica',
            'applied_at' => now()->toDateString(),
            'next_due_at' => now()->addYear()->toDateString(),
            'medical_record_id' => $record['id'],
        ])->assertCreated()->json('data');
        $this->assertSame('Antirrábica', $vax['vaccine_name']);

        $this->withToken($this->token)->postJson('/api/v1/pets/'.$pet['id'].'/dewormings', [
            'product_name' => 'Drontal',
            'next_due_at' => now()->addMonths(3)->toDateString(),
        ])->assertCreated();

        $variant = $this->makeVariant(15);
        $this->stock($variant, 3);
        $this->withToken($this->token)->postJson('/api/v1/appointments/'.$apt['id'].'/consumptions', [
            'variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();
        $this->assertEquals(2, $variant->batches()->sum('quantity'));

        $this->withToken($this->token)->postJson('/api/v1/cash-sessions/open', ['opening_amount' => 100]);
        $sale = $this->withToken($this->token)->postJson('/api/v1/appointments/'.$apt['id'].'/charge', [
            'payments' => [['method' => 'efectivo', 'amount' => 100]],
        ])->assertCreated()->json('data');

        $this->assertSame($customer['id'], $sale['customer_id']);
        $this->assertSame($pet['id'], $sale['pet_id']);
        $this->assertTrue(collect($sale['items'])->contains(fn ($row) => $row['item_type'] === 'service'));
        $this->assertTrue(collect($sale['items'])->contains(fn ($row) => (int) $row['variant_id'] === $variant->id));
        $this->assertEquals(2, (int) $variant->batches()->sum('quantity'));

        $history = $this->withToken($this->token)
            ->getJson('/api/v1/pets/'.$pet['id'].'/clinical-history')
            ->assertOk()
            ->json('data');
        $this->assertNotEmpty($history['vaccinations']);
    }

    public function test_operator_cannot_manage_catalog(): void
    {
        $operator = User::factory()->create(['is_active' => true]);
        $operator->syncRoles(['Operador de tienda']);
        $this->actingAs($operator, 'sanctum')
            ->postJson('/api/v1/services', [
                'code' => 'x-'.$this->id(),
                'name' => 'X',
                'sale_price' => 1,
            ])
            ->assertForbidden();
    }

    private function makeVariant(float $price): Variante
    {
        $categoria = Categoria::query()->create(['nombre' => 'Cli '.$this->id(), 'activo' => true]);
        $producto = Producto::create([
            'nombre' => 'Vacuna '.$this->id(),
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        return Variante::create([
            'producto_id' => $producto->id,
            'sku' => 'VX-'.$this->id(),
            'presentation' => 'dosis',
            'sale_price' => $price,
            'cost_price' => 5,
            'activo' => true,
        ]);
    }

    private function stock(Variante $variant, int $qty): void
    {
        $batch = Batch::create([
            'variant_id' => $variant->id,
            'batch_code' => 'VC-'.$this->id(),
            'quantity' => 0,
            'cost' => 5,
        ]);
        $this->withToken($this->token)->postJson('/api/v1/stock/adjustments', [
            'batch_id' => $batch->id,
            'direction' => 'entrada',
            'quantity' => $qty,
            'reason' => 'carga clinica',
        ])->assertCreated();
    }

    private function id(): string
    {
        return str_replace('.', '', uniqid('', true));
    }
}
