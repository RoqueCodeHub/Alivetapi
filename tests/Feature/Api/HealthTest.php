<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_returns_uniform_json(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['status', 'app', 'env', 'database', 'time'],
            ]);
    }

    public function test_unknown_api_route_returns_uniform_error(): void
    {
        $this->getJson('/api/v1/no-existe')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'data']);
    }

    public function test_sanctum_can_issue_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('e1-test');

        $this->assertNotEmpty($token->plainTextToken);

        Role::findOrCreate('Administrador', 'web');
        $user->assignRole('Administrador');

        $this->assertTrue($user->hasRole('Administrador'));
    }
}
