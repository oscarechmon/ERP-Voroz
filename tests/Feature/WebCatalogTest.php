<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Product;
use Modules\Integration\Services\CatalogNotifier;
use Modules\Packages\Models\Package;
use Modules\Packages\Services\PackageService;
use Tests\TestCase;

/**
 * La web pública es un cascarón: lo que muestra de cada producto, servicio y
 * paquete (si se publica, imagen, descripción, duración) se administra aquí y
 * le llega por la API de integración.
 */
class WebCatalogTest extends TestCase
{
    use RefreshDatabase;

    private array $env;

    protected function setUp(): void
    {
        parent::setUp();

        $this->env = $this->seedErp();
        config(['integration.token' => 'secreto-de-prueba', 'integration.web_url' => null]);
        Storage::fake('public');
    }

    private function api(string $method, string $uri, array $data = []): TestResponse
    {
        return $this->withHeader('X-Integration-Token', 'secreto-de-prueba')
            ->json($method, "/api/v1/integration/{$uri}", $data);
    }

    /** @return array<string, mixed> */
    private function feedItem(Product $product): array
    {
        return $this->api('GET', 'catalog?ids[]='.$product->id)->assertOk()->json('data.0');
    }

    public function test_el_catalogo_trae_lo_que_muestra_la_web(): void
    {
        $category = Category::create(['name' => 'Faciales', 'description' => 'Tratamientos para el rostro', 'is_active' => true]);
        $facial = Product::factory()->service()->create([
            'name' => 'Limpieza facial', 'category_id' => $category->id, 'description' => 'Limpieza profunda',
            'web_published' => true, 'duration_minutes' => 45, 'image_path' => 'products/1/foto.webp',
        ]);

        $item = $this->feedItem($facial);

        $this->assertTrue($item['web_published']);
        $this->assertSame(45, $item['duration_minutes']);
        $this->assertSame('Limpieza profunda', $item['description']);
        $this->assertSame('Tratamientos para el rostro', $item['category_description']);
        $this->assertStringStartsWith('http', $item['image_url'], 'La web la muestra tal cual: dirección completa.');
        $this->assertStringEndsWith('products/1/foto.webp', $item['image_url']);
    }

    public function test_lo_que_nunca_se_decidio_aqui_llega_vacio(): void
    {
        $crema = Product::factory()->create();

        $item = $this->feedItem($crema);

        $this->assertNull($item['web_published'], 'Vacío: la web sigue con lo que tenía.');
        $this->assertNull($item['image_url']);
    }

    public function test_publicar_y_la_duracion_se_guardan_desde_el_formulario(): void
    {
        $this->actingAsRole('Administrador', $this->env['company'], $this->env['branch']);
        $facial = Product::factory()->service()->create();

        $this->putJson("/api/v1/products/{$facial->id}", ['web_published' => true, 'duration_minutes' => 60])
            ->assertOk()
            ->assertJsonPath('data.web_published', true)
            ->assertJsonPath('data.duration_minutes', 60);
    }

    public function test_guardar_sin_decidir_la_publicacion_no_la_cambia(): void
    {
        $this->actingAsRole('Administrador', $this->env['company'], $this->env['branch']);
        $crema = Product::factory()->create(['price' => 40]);

        $this->putJson("/api/v1/products/{$crema->id}", ['price' => 45, 'web_published' => null])->assertOk();

        $this->assertNull($crema->fresh()->web_published);
        $this->assertEquals(45, $crema->fresh()->price);
    }

    public function test_la_web_entrega_su_ficha_con_la_imagen(): void
    {
        $crema = Product::factory()->create();

        $this->withHeader('X-Integration-Token', 'secreto-de-prueba')
            ->post("/api/v1/integration/products/{$crema->id}/web", [
                'description' => 'Crema hidratante de la web',
                'web_published' => '1',
                'image' => UploadedFile::fake()->image('crema.jpg', 1200, 900),
            ])
            ->assertOk()
            ->assertJsonPath('data.web_published', true);

        $crema->refresh();
        $this->assertTrue($crema->web_published);
        $this->assertSame('Crema hidratante de la web', $crema->description);
        $this->assertNotNull($crema->image_path);
        Storage::disk('public')->assertExists($crema->image_path);
    }

    public function test_la_ficha_de_un_paquete_queda_en_el_paquete(): void
    {
        $facial = Product::factory()->service()->create();
        $package = app(PackageService::class)->create([
            'name' => '6 faciales', 'price' => 500, 'total_sessions' => 6, 'is_active' => true, 'service_ids' => [$facial->id],
        ]);

        $this->withHeader('X-Integration-Token', 'secreto-de-prueba')
            ->post("/api/v1/integration/products/{$package->product_id}/web", ['description' => 'Para la web', 'web_published' => '1'])
            ->assertOk();

        $package = Package::findOrFail($package->id);
        $this->assertTrue($package->web_published, 'Se guarda en el paquete: su producto se copia de él.');
        $this->assertSame('Para la web', $package->description);
        $this->assertTrue(Product::findOrFail($package->product_id)->web_published);
    }

    public function test_la_descripcion_de_una_categoria_solo_llega_si_aqui_no_hay_una(): void
    {
        Category::create(['name' => 'Suplementos', 'is_active' => true]);
        Category::create(['name' => 'Faciales', 'description' => 'La de aquí', 'is_active' => true]);

        $this->api('POST', 'categories/describe', ['name' => 'Suplementos', 'description' => 'De la web'])->assertJsonPath('data.updated', true);
        $this->api('POST', 'categories/describe', ['name' => 'Faciales', 'description' => 'De la web'])->assertJsonPath('data.updated', false);

        $this->assertSame('De la web', Category::where('name', 'Suplementos')->value('description'));
        $this->assertSame('La de aquí', Category::where('name', 'Faciales')->value('description'));
    }

    public function test_un_cambio_de_categoria_se_avisa_a_la_web(): void
    {
        $category = Category::create(['name' => 'Faciales', 'is_active' => true]);
        $facial = Product::factory()->service()->create(['category_id' => $category->id]);
        config(['integration.web_url' => 'https://web.test']);
        Http::fake(['web.test/*' => Http::response(['ok' => true])]);

        $category->update(['description' => 'Nueva descripción']);
        app(CatalogNotifier::class)->flush();

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://web.test/erp/catalogo'
            && $request['items'][0]['id'] === $facial->id
            && $request['items'][0]['category_description'] === 'Nueva descripción');
    }
}
