<?php

namespace Tests\Feature\Api;

use App\Models\Batch;
use App\Models\Categoria;
use App\Models\Invoice;
use App\Models\Producto;
use App\Models\User;
use App\Models\Variante;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InvoiceV1Test extends TestCase
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
        $this->flushHeaders();
        $this->token = $this->admin->createToken('api')->plainTextToken;
    }

    public function test_boleta_splits_igv_and_fake_pse_accepts(): void
    {
        $sale = $this->sell(118, 1);

        $invoice = $this->withToken($this->token)
            ->postJson('/api/v1/sales/'.$sale['id'].'/invoices', ['type' => 'boleta'])
            ->assertCreated()
            ->json('data');

        $this->assertSame('boleta', $invoice['document_type']);
        $this->assertSame('B001', $invoice['series']);
        $this->assertGreaterThan(0, (int) $invoice['number']);
        $this->assertEquals(100.0, (float) $invoice['gravada']);
        $this->assertEquals(18.0, (float) $invoice['igv']);
        $this->assertEquals(118.0, (float) $invoice['total']);
        $this->assertSame(Invoice::STATUS_ACCEPTED, $invoice['status']);
        $this->assertSame('fake', $invoice['pse_driver']);
        $this->assertNotEmpty($invoice['pse_ticket']);

        $again = $this->withToken($this->token)
            ->postJson('/api/v1/sales/'.$sale['id'].'/invoices', ['type' => 'boleta'])
            ->json('data');
        $this->assertSame($invoice['id'], $again['id']);

        $this->withToken($this->token)->getJson('/api/v1/invoices/series')
            ->assertOk()
            ->assertJsonPath('data.0.series', 'B001');
    }

    public function test_factura_requires_ruc_and_uses_f001(): void
    {
        $sale = $this->sell(23.6, 1);

        $this->withToken($this->token)
            ->postJson('/api/v1/sales/'.$sale['id'].'/invoices', ['type' => 'factura'])
            ->assertStatus(422);

        $customer = $this->withToken($this->token)->postJson('/api/v1/customers', [
            'name' => 'Empresa Vet SAC',
            'document_type' => 'RUC',
            'document_number' => '20'.(string) random_int(100000000, 999999999),
        ])->assertCreated()->json('data');

        $variant = $this->makeVariant(23.6);
        $this->stockBatch($variant, 2, now()->addMonth()->toDateString());
        $factSale = $this->withToken($this->token)->postJson('/api/v1/sales', [
            'idempotency_key' => 'inv-f-'.$this->id(),
            'customer_id' => $customer['id'],
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'payments' => [['method' => 'efectivo', 'amount' => 23.6]],
        ])->assertCreated()->json('data');

        $invoice = $this->withToken($this->token)
            ->postJson('/api/v1/sales/'.$factSale['id'].'/invoices', ['type' => 'factura'])
            ->assertCreated()
            ->json('data');

        $this->assertSame('F001', $invoice['series']);
        $this->assertSame($customer['document_number'], $invoice['customer_doc_number']);
        $this->assertEqualsWithDelta(3.6, (float) $invoice['igv'], 0.01);
    }

    public function test_void_of_invoiced_sale_creates_credit_note(): void
    {
        $sale = $this->sell(118, 2);
        $this->withToken($this->token)
            ->postJson('/api/v1/sales/'.$sale['id'].'/invoices', ['type' => 'boleta'])
            ->assertCreated();

        $this->withToken($this->token)->postJson('/api/v1/sales/'.$sale['id'].'/void', [
            'reason' => 'Cliente desistió',
        ])->assertOk();

        $notes = Invoice::query()
            ->where('sale_id', $sale['id'])
            ->where('document_type', Invoice::TYPE_CREDIT_NOTE)
            ->get();
        $this->assertCount(1, $notes);
        $nc = $notes->first();
        $this->assertSame('BC01', $nc->series);
        $this->assertSame('01', $nc->credit_note_reason);
        $this->assertSame(Invoice::STATUS_ACCEPTED, $nc->status);
        $this->assertEquals(236.0, (float) $nc->total);
    }

    public function test_almacenero_cannot_view_invoices(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->syncRoles(['Almacenero']);
        $this->flushHeaders();
        $this->actingAs($user, 'sanctum');
        $this->getJson('/api/v1/invoices')->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function sell(float $price, int $qty): array
    {
        $variant = $this->makeVariant($price);
        $this->stockBatch($variant, $qty + 2, now()->addMonth()->toDateString());
        $this->withToken($this->token)->postJson('/api/v1/cash-sessions/open', ['opening_amount' => 50]);

        return $this->withToken($this->token)->postJson('/api/v1/sales', [
            'idempotency_key' => 'inv-'.$this->id(),
            'items' => [['variant_id' => $variant->id, 'quantity' => $qty]],
            'payments' => [['method' => 'efectivo', 'amount' => $price * $qty]],
        ])->assertCreated()->json('data');
    }

    private function makeVariant(float $price): Variante
    {
        $categoria = Categoria::query()->create(['nombre' => 'INV '.$this->id(), 'activo' => true]);
        $producto = Producto::create([
            'nombre' => 'Prod INV '.$this->id(),
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        return Variante::create([
            'producto_id' => $producto->id,
            'sku' => 'INV-'.$this->id(),
            'presentation' => 'unidad',
            'unit' => 'unidad',
            'sale_price' => $price,
            'cost_price' => 1,
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
            'cost' => 1,
        ]);
        $this->withToken($this->token)->postJson('/api/v1/stock/adjustments', [
            'batch_id' => $batch->id,
            'direction' => 'entrada',
            'quantity' => $qty,
            'reason' => 'carga invoice test',
        ])->assertCreated();

        return $batch->fresh();
    }

    private function id(): string
    {
        return str_replace('.', '', uniqid('', true));
    }
}
