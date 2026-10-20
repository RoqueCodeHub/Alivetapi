<?php

namespace Tests\Feature\Api;

use App\Models\StockMovement;
use App\Models\User;
use App\Models\Variante;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ImportInventoryV1Test extends TestCase
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

    public function test_imports_csv_creates_batch_and_marks_review(): void
    {
        $suffix = uniqid();
        $csv = implode("\n", [
            'categoría,producto,presentación,cantidad,unidad,F.V.,costo,precio,observaciones',
            "Medicamentos,Biomec {$suffix} 1% L.A. — 100 mL,,10,ml,agosto-2028,12.5,25,",
            "Ropa,Bozal cuero {$suffix},,5,unidad,,8,20,revisar talla",
        ]);

        $file = UploadedFile::fake()->createWithContent('inventario.csv', $csv);

        $response = $this->withToken($this->token)
            ->post('/api/v1/import/inventory', ['file' => $file])
            ->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertSame(2, $data['processed']);
        $this->assertSame(2, $data['imported']);
        $this->assertSame(0, $data['rejected']);
        $this->assertGreaterThanOrEqual(1, $data['pending_review']);

        $biomec = Variante::query()
            ->whereHas('producto', fn ($q) => $q->where('nombre', 'Biomec '.$suffix.' 1%'))
            ->first();

        $this->assertNotNull($biomec);
        $this->assertSame('2028-08-31', optional($biomec->batches()->first()?->expiry_date)?->toDateString());
        $this->assertTrue($biomec->batches()->where('quantity', 10)->exists());
        $this->assertTrue(StockMovement::query()->where('variant_id', $biomec->id)->where('type', 'inicial')->exists());

        $pending = $this->withToken($this->token)
            ->getJson('/api/v1/inventory/pending-review')
            ->assertOk()
            ->json('data.items');

        $this->assertNotEmpty($pending);

        $bozal = Variante::query()
            ->whereHas('producto', fn ($q) => $q->where('nombre', 'Bozal cuero '.$suffix))
            ->first();
        $this->assertTrue((bool) $bozal->needs_review);

        $this->withToken($this->token)
            ->putJson('/api/v1/inventory/pending-review/'.$bozal->id, [
                'presentation' => 'Talla M',
                'sale_price' => 20,
                'needs_review' => false,
                'review_note' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.needs_review', false);
    }

    public function test_rejects_rows_without_product_name(): void
    {
        $csv = "categoría,producto,cantidad\nMedicamentos,,3\n";
        $file = UploadedFile::fake()->createWithContent('bad.csv', $csv);

        $this->withToken($this->token)
            ->post('/api/v1/import/inventory', ['file' => $file])
            ->assertOk()
            ->assertJsonPath('data.rejected', 1)
            ->assertJsonPath('data.imported', 0);
    }

    public function test_operator_without_adjust_cannot_import(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles(['Cliente']);
        $token = $user->createToken('api')->plainTextToken;
        $file = UploadedFile::fake()->createWithContent('inv.csv', "producto\nX\n");

        $this->withToken($token)
            ->post('/api/v1/import/inventory', ['file' => $file])
            ->assertForbidden();
    }
}
