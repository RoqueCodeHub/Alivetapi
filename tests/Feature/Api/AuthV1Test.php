<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthV1Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['sanctum.stateful' => []]);
        $this->seed(PermissionSeeder::class);
    }

    public function test_admin_and_operator_can_login(): void
    {
        $admin = $this->makeUser('Administrador');
        $operator = $this->makeUser('Operador de tienda');

        $adminLogin = $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertOk()->assertJsonPath('success', true);

        $operatorLogin = $this->postJson('/api/v1/auth/login', [
            'email' => $operator->email,
            'password' => 'password',
        ])->assertOk();

        $this->assertContains('Administrador', $adminLogin->json('data.user.roles'));
        $this->assertContains('Operador de tienda', $operatorLogin->json('data.user.roles'));
        $this->assertNotEquals($adminLogin->json('data.token'), $operatorLogin->json('data.token'));
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = $this->makeUser('Operador de tienda', false);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertUnauthorized()->assertJsonPath('success', false);
    }

    public function test_operator_cannot_list_users(): void
    {
        $operator = $this->makeUser('Operador de tienda');
        $token = $operator->createToken('api')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/users')
            ->assertForbidden();
    }

    public function test_admin_can_create_user_with_role(): void
    {
        $admin = $this->makeUser('Administrador');
        $token = $admin->createToken('api')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/users', [
            'name' => 'Cajero Uno',
            'email' => 'cajero.'.uniqid().'@example.com',
            'password' => 'password1',
            'roles' => ['Cajero'],
        ])->assertCreated()->assertJsonPath('data.roles.0', 'Cajero');
    }

    public function test_logout_revokes_token(): void
    {
        $user = $this->makeUser('Administrador');
        $token = $user->createToken('api')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$token])->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'api',
        ]);
    }

    private function makeUser(string $role, bool $active = true): User
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'is_active' => $active,
        ]);
        $user->syncRoles([$role]);

        return $user;
    }
}
