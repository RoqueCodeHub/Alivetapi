<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\VetService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PortalV1Test extends TestCase
{
    private string $staffToken;

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
        $this->flushHeaders();
        $this->staffToken = $admin->createToken('api')->plainTextToken;
    }

    public function test_portal_login_sees_own_data_not_other_customers(): void
    {
        $this->withToken($this->staffToken)->getJson('/api/v1/services')->assertOk();
        $service = VetService::query()->where('code', 'consulta')->first();

        $mine = $this->withToken($this->staffToken)->postJson('/api/v1/customers', [
            'name' => 'Portal Uno '.$this->id(),
            'document_type' => 'DNI',
            'document_number' => (string) random_int(40000000, 49999999),
        ])->assertCreated()->json('data');

        $other = $this->withToken($this->staffToken)->postJson('/api/v1/customers', [
            'name' => 'Portal Dos '.$this->id(),
            'document_type' => 'DNI',
            'document_number' => (string) random_int(50000000, 59999999),
        ])->assertCreated()->json('data');

        $myPet = $this->withToken($this->staffToken)->postJson('/api/v1/customers/'.$mine['id'].'/pets', [
            'nombre' => 'Michi',
            'especie' => 'felino',
        ])->assertCreated()->json('data');

        $otherPet = $this->withToken($this->staffToken)->postJson('/api/v1/customers/'.$other['id'].'/pets', [
            'nombre' => 'Rocky',
            'especie' => 'canino',
        ])->assertCreated()->json('data');

        $this->withToken($this->staffToken)->postJson('/api/v1/pets/'.$myPet['id'].'/vaccinations', [
            'vaccine_name' => 'Triple felina',
            'applied_at' => now()->toDateString(),
            'next_due_at' => now()->addYear()->toDateString(),
        ])->assertCreated();

        $email = 'portal.'.$this->id().'@example.com';
        $this->withToken($this->staffToken)
            ->postJson('/api/v1/customers/'.$mine['id'].'/portal', [
                'email' => $email,
                'password' => 'portal123',
            ])
            ->assertCreated();

        $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'portal123',
        ])->assertForbidden();

        $login = $this->postJson('/api/v1/portal/login', [
            'email' => $email,
            'password' => 'portal123',
        ])->assertOk()->json('data');

        $this->assertSame($mine['id'], $login['user']['cliente_id']);
        $this->assertContains('Cliente', $login['user']['roles']);
        $token = $login['token'];

        $pets = $this->withToken($token)->getJson('/api/v1/portal/pets')->assertOk()->json('data');
        $this->assertCount(1, $pets);
        $this->assertSame('Michi', $pets[0]['nombre']);

        $carnet = $this->withToken($token)
            ->getJson('/api/v1/portal/pets/'.$myPet['id'].'/carnet')
            ->assertOk()
            ->json('data');
        $this->assertSame('Triple felina', $carnet['vaccinations'][0]['vaccine_name']);

        $this->withToken($token)
            ->getJson('/api/v1/portal/pets/'.$otherPet['id'].'/carnet')
            ->assertStatus(422);

        $when = now()->addDays(3)->format('Y-m-d H:i:s');
        $this->withToken($token)->postJson('/api/v1/portal/appointments', [
            'pet_id' => $myPet['id'],
            'clinic_service_id' => $service->id,
            'scheduled_at' => $when,
        ])->assertCreated()->assertJsonPath('data.status', 'pendiente');

        $this->withToken($token)->postJson('/api/v1/portal/appointments', [
            'pet_id' => $otherPet['id'],
            'clinic_service_id' => $service->id,
            'scheduled_at' => $when,
        ])->assertStatus(422);

        $citas = $this->withToken($token)->getJson('/api/v1/portal/appointments')->assertOk()->json('data');
        $this->assertNotEmpty($citas['upcoming']);

        $this->withToken($token)->getJson('/api/v1/portal/purchases')->assertOk();
        $this->withToken($token)->getJson('/api/v1/users')->assertForbidden();
    }

    private function id(): string
    {
        return str_replace('.', '', uniqid('', true));
    }
}
