<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CatalogV1Test extends TestCase
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

    public function test_can_create_category_tree_brand_product_and_variant(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->token];

        $parent = $this->withHeaders($headers)->postJson('/api/v1/categories', [
            'nombre' => 'Medicamentos Test '.uniqid(),
        ])->assertCreated()->json('data');

        $child = $this->withHeaders($headers)->postJson('/api/v1/categories', [
            'nombre' => 'antibióticos '.uniqid(),
            'parent_id' => $parent['id'],
        ])->assertCreated()->json('data');

        $this->assertSame($parent['id'], $child['parent_id']);

        $brand = $this->withHeaders($headers)->postJson('/api/v1/brands', [
            'nombre' => 'Marca '.uniqid(),
        ])->assertCreated()->json('data');

        $product = $this->withHeaders($headers)->postJson('/api/v1/products', [
            'nombre' => 'Bozal',
            'categoria_id' => $child['id'],
            'marca_id' => $brand['id'],
            'especie' => 'canino',
            'tipo' => 'accesorio',
            'requires_batch' => false,
        ])->assertCreated()->json('data');

        $variant = $this->withHeaders($headers)->postJson('/api/v1/products/'.$product['id'].'/variants', [
            'sku' => 'BOZ-S-'.uniqid(),
            'presentation' => 'Talla S',
            'unit' => 'unidad',
            'sale_price' => 25.5,
        ])->assertCreated()->json('data');

        $this->assertSame('Talla S', $variant['presentation']);

        $this->withHeaders($headers)
            ->getJson('/api/v1/products?nombre=Bozal')
            ->assertOk()
            ->assertJsonPath('success', true);

        $sized = $this->withHeaders($headers)->postJson('/api/v1/products', [
            'nombre' => 'Correa',
            'presentacion' => null,
            'talla' => 'L',
            'categoria_id' => $child['id'],
            'precio_compra' => 30,
            'precio_venta' => 0,
            'stock_actual' => 5,
        ])->assertCreated()->json('data');
        $this->assertSame('L', $sized['talla']);
        $this->assertNotEmpty($sized['codigo']);

        $this->withHeaders($headers)
            ->getJson('/api/v1/products?q=L&talla=L')
            ->assertOk();

        $this->withHeaders($headers)
            ->deleteJson('/api/v1/products/'.$product['id'])
            ->assertOk()
            ->assertJsonPath('message', 'Producto desactivado porque tiene historial');

        $this->assertTrue(Producto::find($product['id'])->activo === false);
        $this->assertNotNull(Categoria::find($parent['id']));
        $this->assertNotNull(Marca::find($brand['id']));
    }

    public function test_can_filter_and_paginate_categories(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->token];
        $suffix = uniqid();
        $root = Categoria::query()->create([
            'nombre' => 'Filtro raiz '.$suffix,
            'activo' => true,
        ]);
        Categoria::query()->create([
            'nombre' => 'Filtro hija '.$suffix,
            'parent_id' => $root->id,
            'activo' => true,
        ]);

        $this->withHeaders($headers)
            ->getJson('/api/v1/categories?page=1&per_page=10&q='.urlencode('Filtro hija '.$suffix))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.nombre', 'Filtro hija '.$suffix)
            ->assertJsonPath('data.items.0.parent.nombre', 'Filtro raiz '.$suffix);

        $this->withHeaders($headers)
            ->getJson('/api/v1/categories?page=1&parent_id=root&q='.urlencode('Filtro raiz '.$suffix))
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);

        $this->withHeaders($headers)
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_can_delete_empty_category_but_not_one_with_children_or_products(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->token];
        $suffix = uniqid();
        $parent = Categoria::query()->create([
            'nombre' => 'Borrar padre '.$suffix,
            'activo' => true,
        ]);
        $child = Categoria::query()->create([
            'nombre' => 'Borrar hija '.$suffix,
            'parent_id' => $parent->id,
            'activo' => true,
        ]);
        $empty = Categoria::query()->create([
            'nombre' => 'Borrar vacia '.$suffix,
            'activo' => true,
        ]);

        $this->withHeaders($headers)
            ->deleteJson('/api/v1/categories/'.$parent->id)
            ->assertStatus(422);

        Producto::create([
            'nombre' => 'Prod cat '.$suffix,
            'categoria_id' => $child->id,
            'activo' => true,
        ]);

        $this->withHeaders($headers)
            ->deleteJson('/api/v1/categories/'.$child->id)
            ->assertStatus(422);

        $this->withHeaders($headers)
            ->deleteJson('/api/v1/categories/'.$empty->id)
            ->assertOk()
            ->assertJsonPath('message', 'Categoría eliminada');

        $this->assertNull(Categoria::query()->find($empty->id));
    }
}
