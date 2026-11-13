<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SummaryV1Test extends TestCase
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

    public function test_paginated_lists_include_summary_counts(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->token];
        $lists = [
            '/api/v1/categories?page=1' => ['total', 'principales', 'subcategorias', 'activas'],
            '/api/v1/brands?page=1' => ['total', 'activas', 'inactivas', 'con_productos'],
            '/api/v1/products?page=1' => ['total', 'activos', 'stock_bajo', 'por_vencer'],
            '/api/v1/stock?page=1' => ['total', 'bajos', 'sin_stock', 'por_vencer'],
            '/api/v1/stock/counts?page=1' => ['total', 'borradores', 'aprobados', 'de_hoy'],
            '/api/v1/suppliers?page=1' => ['total', 'activos', 'con_ruc', 'con_telefono'],
            '/api/v1/purchases?page=1' => ['total', 'borradores', 'confirmadas', 'por_pagar'],
            '/api/v1/purchases/payables?page=1' => ['documentos', 'saldo', 'vencidos', 'vigentes'],
            '/api/v1/customers?page=1' => ['total', 'activos', 'inactivos', 'documentados'],
            '/api/v1/pets?page=1' => ['total', 'con_foto', 'con_especie', 'sin_especie'],
            '/api/v1/sales?page=1' => ['total', 'completadas', 'pendientes', 'anuladas', 'monto'],
            '/api/v1/invoices?page=1' => ['total', 'aceptados', 'rechazados', 'en_cola'],
            '/api/v1/services?page=1' => ['total', 'activos', 'inactivos', 'peluqueria'],
            '/api/v1/appointments?page=1' => ['total', 'pendientes', 'atendidas', 'canceladas'],
            '/api/v1/reminders?page=1' => ['total', 'pendientes', 'clientes', 'internos'],
            '/api/v1/users?page=1' => ['total', 'activos', 'inactivos', 'recientes'],
        ];

        foreach ($lists as $path => $keys) {
            $summary = $this->withHeaders($headers)->getJson($path)->assertOk()->json('data.summary');
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $summary, $path);
                $this->assertIsNumeric($summary[$key], $path.' '.$key);
            }
        }
    }
}
