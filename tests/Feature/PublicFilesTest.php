<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Las fotos subidas se ven aunque falte el enlace public/storage: la web
 * pública las muestra desde aquí y no puede quedarse con imágenes rotas.
 */
class PublicFilesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    #[Test]
    public function sirve_la_foto_de_un_producto_sin_el_enlace(): void
    {
        Storage::disk('public')->put('products/21/img_abc.webp', 'contenido-de-la-foto');

        $response = $this->get('/storage/products/21/img_abc.webp')->assertOk();

        $this->assertSame('contenido-de-la-foto', $response->streamedContent());
        $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
        $response->assertCookieMissing(config('session.cookie'));
    }

    #[Test]
    public function lo_que_no_existe_o_sale_del_disco_es_404(): void
    {
        $this->get('/storage/products/21/no-existe.webp')->assertNotFound();
        $this->get('/storage/../../.env')->assertNotFound();
        $this->get('/storage/products/..%2F..%2F..%2F.env')->assertNotFound();
    }
}
