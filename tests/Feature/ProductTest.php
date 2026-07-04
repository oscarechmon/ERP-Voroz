<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Product;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_product_auto_generates_code_and_barcode(): void
    {
        $env = $this->seedErp();
        $this->actingAsRole('Administrador', $env['company'], $env['branch']);

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Gaseosa 500ml', 'cost' => 2.5, 'price' => 4.0,
        ])->assertCreated();

        $response->assertJsonPath('data.name', 'Gaseosa 500ml');
        $this->assertStringStartsWith('PRD-', $response->json('data.code'));
        $this->assertNotEmpty($response->json('data.barcode'));           // EAN-13 autogenerado
        $this->assertEquals(37.5, $response->json('data.profit_margin')); // (4-2.5)/4
    }

    public function test_seller_role_cannot_create_products(): void
    {
        $env = $this->seedErp();
        $this->actingAsRole('Ventas', $env['company'], $env['branch']);

        $this->postJson('/api/v1/products', ['name' => 'X', 'cost' => 1, 'price' => 2])
            ->assertForbidden();
    }

    public function test_product_index_is_paginated_and_searchable(): void
    {
        $env = $this->seedErp();
        $this->actingAsRole('Administrador', $env['company'], $env['branch']);
        Product::factory()->count(20)->create();
        Product::factory()->create(['name' => 'Producto Único Especial']);

        $this->getJson('/api/v1/products?search=Único Especial')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_product_requires_a_name(): void
    {
        $env = $this->seedErp();
        $this->actingAsRole('Administrador', $env['company'], $env['branch']);

        $this->postJson('/api/v1/products', ['cost' => 1, 'price' => 2])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }
}
