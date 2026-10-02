<?php

namespace Tests\Feature;

use Illuminate\Console\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

/**
 * Publicación de una versión subida como zip.
 *
 * Se descomprime en una carpeta temporal, nunca sobre el proyecto.
 */
class ReleaseDeployTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('framework/testing/release-'.uniqid());
        File::ensureDirectoryExists($this->path);

        config(['deploy.token' => 'token-de-prueba', 'deploy.path' => $this->path, 'deploy.chunk' => 1200]);

        // Las migraciones y cachés reales no aportan nada aquí.
        Artisan::swap(new class extends Application
        {
            public function __construct() {}

            public function call($command, array $parameters = [], $outputBuffer = null): int
            {
                return 0;
            }

            public function output(): string
            {
                return '';
            }
        });
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);
        parent::tearDown();
    }

    /** @param array<string,string> $files */
    private function makeArchive(array $files, string $name = 'release.zip'): string
    {
        $archivePath = $this->path.'/'.$name;
        $zip = new ZipArchive;
        $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $entry => $contents) {
            $zip->addFromString($entry, $contents);
        }

        $zip->close();

        return $archivePath;
    }

    private function publish(): TestResponse
    {
        return $this->withHeader('X-Deploy-Token', 'token-de-prueba')->postJson('/deploy/release');
    }

    #[Test]
    public function descomprime_el_paquete_y_lo_borra_al_terminar(): void
    {
        $archive = $this->makeArchive([
            'public/index.php' => '<?php echo "nuevo";',
            'app/Support/Ejemplo.php' => '<?php // nuevo',
        ]);

        $this->publish()->assertOk()->assertJson(['done' => true, 'total' => 2]);

        $this->assertSame('<?php echo "nuevo";', file_get_contents($this->path.'/public/index.php'));
        $this->assertFileExists($this->path.'/app/Support/Ejemplo.php');
        $this->assertFileDoesNotExist($archive, 'El zip debe borrarse tras publicarlo.');
    }

    #[Test]
    public function sobrescribe_la_version_anterior(): void
    {
        File::ensureDirectoryExists($this->path.'/public');
        File::put($this->path.'/public/index.php', 'viejo');

        $this->makeArchive(['public/index.php' => 'nuevo']);
        $this->publish()->assertOk();

        $this->assertSame('nuevo', file_get_contents($this->path.'/public/index.php'));
    }

    /** Un zip grande se extrae en varias llamadas, sin perder el hilo. */
    #[Test]
    public function descomprime_por_tandas(): void
    {
        config(['deploy.chunk' => 2]);
        $this->makeArchive(['a.txt' => 'a', 'b.txt' => 'b', 'c.txt' => 'c', 'd.txt' => 'd', 'e.txt' => 'e']);

        $this->publish()->assertOk()->assertJson(['done' => false, 'extraidos' => 2, 'total' => 5]);
        $this->publish()->assertOk()->assertJson(['done' => false, 'extraidos' => 4, 'total' => 5]);
        $this->publish()->assertOk()->assertJson(['done' => true, 'total' => 5]);

        foreach (['a', 'b', 'c', 'd', 'e'] as $letra) {
            $this->assertFileExists($this->path."/{$letra}.txt");
        }
    }

    /** Un zip nuevo empieza de cero aunque el anterior quedara a medias. */
    #[Test]
    public function un_paquete_distinto_reinicia_la_extraccion(): void
    {
        config(['deploy.chunk' => 1]);
        $this->makeArchive(['uno.txt' => '1', 'dos.txt' => '2']);
        $this->publish()->assertOk()->assertJson(['done' => false, 'extraidos' => 1]);

        // Llega otra versión antes de terminar la anterior.
        sleep(1);
        $this->makeArchive(['tres.txt' => '3', 'cuatro.txt' => '4', 'cinco.txt' => '5']);

        $this->publish()->assertOk()->assertJson(['done' => false, 'extraidos' => 1, 'total' => 3]);
        $this->publish()->assertOk()->assertJson(['done' => false, 'extraidos' => 2]);
        $this->publish()->assertOk()->assertJson(['done' => true, 'total' => 3]);

        $this->assertFileExists($this->path.'/tres.txt');
        $this->assertFileDoesNotExist($this->path.'/uno.txt', 'Lo extraído del paquete abandonado no se publica.');
    }

    /**
     * Mientras el paquete no esté entero, la aplicación en marcha no ve nada:
     * si arrancara con medio vendor/ nuevo, la siguiente tanda ya no podría
     * ni responder.
     */
    #[Test]
    public function no_toca_la_aplicacion_hasta_tener_el_paquete_completo(): void
    {
        config(['deploy.chunk' => 1]);
        File::put($this->path.'/viejo.txt', 'version anterior');
        $this->makeArchive(['viejo.txt' => 'version nueva', 'otro.txt' => 'nuevo']);

        $this->publish()->assertOk()->assertJson(['done' => false]);

        $this->assertSame('version anterior', file_get_contents($this->path.'/viejo.txt'));
        $this->assertFileDoesNotExist($this->path.'/otro.txt');

        $this->publish()->assertOk()->assertJson(['done' => true, 'publicados' => 2]);

        $this->assertSame('version nueva', file_get_contents($this->path.'/viejo.txt'));
        $this->assertFileExists($this->path.'/otro.txt');
        $this->assertDirectoryDoesNotExist($this->path.'/.release-tmp');
    }

    #[Test]
    public function rechaza_rutas_que_escapan_de_la_carpeta(): void
    {
        $this->makeArchive(['../fuera.txt' => 'no']);

        $this->publish()->assertStatus(409)->assertJsonPath('done', false);

        $this->assertFileDoesNotExist(dirname($this->path).'/fuera.txt');
    }

    #[Test]
    public function avisa_cuando_no_hay_paquete(): void
    {
        $this->publish()->assertStatus(409)->assertJsonFragment(['done' => false]);
    }

    #[Test]
    public function sin_token_correcto_no_publica_nadie(): void
    {
        $this->makeArchive(['public/index.php' => 'nuevo']);

        $this->postJson('/deploy/release')->assertForbidden();
        $this->withHeader('X-Deploy-Token', 'otro')->postJson('/deploy/release')->assertForbidden();

        $this->assertFileDoesNotExist($this->path.'/public/index.php');
    }

    #[Test]
    public function sin_token_configurado_la_ruta_no_existe(): void
    {
        config(['deploy.token' => '']);

        $this->postJson('/deploy/release')->assertNotFound();
    }
}
