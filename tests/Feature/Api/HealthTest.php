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

    public function test_public_responses_hide_fields_and_send_security_headers(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'")
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');

        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Los datos enviados no son válidos')
            ->assertJsonMissingPath('errors');
    }

    public function test_production_forces_debug_off_and_https(): void
    {
        $this->swapEnvironment('production');

        try {
            $this->assertFalse((bool) config('app.debug'));
            $this->assertTrue((bool) config('app.force_https'));
        } finally {
            $this->swapEnvironment('testing');
        }
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

    private function swapEnvironment(string $env): void
    {
        putenv('APP_ENV='.$env);
        $_ENV['APP_ENV'] = $env;
        $_SERVER['APP_ENV'] = $env;
        $this->refreshApplication();
        config(['sanctum.stateful' => []]);
    }
}
