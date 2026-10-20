<?php

namespace Tests\Feature\Api;

use App\Mail\ReminderMail;
use App\Models\Batch;
use App\Models\CashSession;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Reminder;
use App\Models\User;
use App\Models\Variante;
use App\Models\VetService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReminderV1Test extends TestCase
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

    public function test_scheduler_generates_and_emails_reminders(): void
    {
        Mail::fake();

        $this->withToken($this->token)->getJson('/api/v1/services')->assertOk();
        $service = VetService::query()->where('code', 'consulta')->first();

        $customer = $this->withToken($this->token)->postJson('/api/v1/customers', [
            'name' => 'Rosa Rem '.$this->id(),
            'document_type' => 'DNI',
            'document_number' => (string) random_int(30000000, 39999999),
            'whatsapp' => '987111222',
        ])->assertCreated()->json('data');
        Cliente::query()->find($customer['id'])->persona?->update(['email' => 'rosa-'.$this->id().'@test.com']);

        $pet = $this->withToken($this->token)->postJson('/api/v1/customers/'.$customer['id'].'/pets', [
            'nombre' => 'Kira',
        ])->assertCreated()->json('data');

        $this->withToken($this->token)->postJson('/api/v1/pets/'.$pet['id'].'/vaccinations', [
            'vaccine_name' => 'Antirrábica',
            'next_due_at' => now()->toDateString(),
        ])->assertCreated();
        $this->withToken($this->token)->postJson('/api/v1/pets/'.$pet['id'].'/dewormings', [
            'product_name' => 'Drontal',
            'next_due_at' => now()->addDays(3)->toDateString(),
        ])->assertCreated();

        $this->withToken($this->token)->postJson('/api/v1/appointments', [
            'pet_id' => $pet['id'],
            'clinic_service_id' => $service->id,
            'staff_user_id' => $this->admin->id,
            'scheduled_at' => now()->addDay()->toDateTimeString(),
        ])->assertCreated();

        $categoria = Categoria::query()->create(['nombre' => 'Rem '.$this->id(), 'activo' => true]);
        $producto = Producto::create(['nombre' => 'Amox '.$this->id(), 'categoria_id' => $categoria->id, 'activo' => true]);
        $variant = Variante::create([
            'producto_id' => $producto->id,
            'sku' => 'RM-'.$this->id(),
            'presentation' => 'u',
            'sale_price' => 10,
            'cost_price' => 2,
            'min_stock' => 10,
            'activo' => true,
        ]);
        Batch::create([
            'variant_id' => $variant->id,
            'batch_code' => 'BX-'.$this->id(),
            'quantity' => 2,
            'cost' => 2,
            'expiry_date' => now()->addDays(10)->toDateString(),
        ]);

        CashSession::create([
            'user_id' => $this->admin->id,
            'opened_at' => now()->subDay(),
            'closed_at' => now()->subDay(),
            'opening_amount' => 50,
            'expected_amount' => 50,
            'counted_amount' => 40,
            'difference' => -10,
        ]);

        $this->artisan('reminders:run')->assertSuccessful();
        $this->artisan('reminders:run')->assertSuccessful();

        $this->assertTrue(Reminder::query()->where('type', Reminder::TYPE_VACUNA)->exists());
        $this->assertTrue(Reminder::query()->where('type', Reminder::TYPE_DESPARASITACION)->exists());
        $this->assertTrue(Reminder::query()->where('type', Reminder::TYPE_CITA)->exists());
        $this->assertTrue(Reminder::query()->where('type', Reminder::TYPE_VENCIMIENTO)->exists());
        $this->assertTrue(Reminder::query()->where('type', Reminder::TYPE_STOCK)->exists());
        $this->assertTrue(Reminder::query()->where('type', Reminder::TYPE_CAJA)->exists());

        $vax = Reminder::query()->where('type', Reminder::TYPE_VACUNA)->first();
        $this->assertStringContainsString('wa.me/51987111222', (string) $vax->whatsapp_url);

        Mail::assertSent(ReminderMail::class);

        $list = $this->withToken($this->token)->getJson('/api/v1/reminders')->assertOk()->json('data.items');
        $this->assertNotEmpty($list);

        $this->withToken($this->token)
            ->postJson('/api/v1/reminders/'.$vax->id.'/dismiss')
            ->assertOk()
            ->assertJsonPath('data.status', Reminder::STATUS_DISMISSED);

        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $kernel = app(\App\Console\Kernel::class);
        $method = new \ReflectionMethod($kernel, 'schedule');
        $method->invoke($kernel, $schedule);
        $listed = collect($schedule->events())->map(fn ($event) => $event->command ?? '')->implode(' | ');
        $this->assertStringContainsString('reminders:run', $listed);
    }

    private function id(): string
    {
        return str_replace('.', '', uniqid('', true));
    }
}
