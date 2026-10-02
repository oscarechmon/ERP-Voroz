<?php

namespace App\Http\Controllers;

use App\Providers\ModuleServiceProvider;
use FilesystemIterator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

/**
 * Publica la versión subida como `release.zip` (ver DEPLOY.md).
 *
 * Subir el proyecto archivo por archivo son miles de transferencias FTP y el
 * servidor corta la sesión a la hora. En su lugar, GitHub Actions sube un
 * único zip y esta ruta lo publica en el servidor.
 *
 * Se descomprime por tandas: cada llamada extrae un bloque de entradas y
 * devuelve por dónde va, así ninguna petición se pasa del tiempo máximo de
 * ejecución de PHP.
 *
 * Las tandas van a una carpeta de paso, no sobre la aplicación: entre una
 * llamada y otra Laravel tiene que arrancar, y hacerlo con medio vendor/ nuevo
 * y medio viejo lo rompe. Solo cuando el paquete está entero se mueven los
 * archivos a su sitio, uno a uno, y recién ahí se migra y se cachea.
 */
class ReleaseController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->verifyDeployToken($request);

        if (! class_exists(ZipArchive::class)) {
            return $this->fail('Este PHP no tiene la extensión zip: actívala en hPanel.');
        }

        $path = rtrim(config('deploy.path') ?: base_path(), DIRECTORY_SEPARATOR.'/');
        $archivePath = $path.DIRECTORY_SEPARATOR.config('deploy.archive');

        if (! is_file($archivePath)) {
            return $this->fail('No hay '.config('deploy.archive').' que publicar: primero súbelo por FTP.');
        }

        $zip = new ZipArchive;

        if ($zip->open($archivePath) !== true) {
            return $this->fail('El archivo subido no es un zip válido o llegó incompleto.');
        }

        // La firma distingue una subida nueva de la que se estaba extrayendo,
        // para no continuar por la mitad de un zip que ya no es el mismo.
        $signature = filesize($archivePath).'-'.filemtime($archivePath);
        $statePath = storage_path('framework/release-state.json');
        $state = json_decode((string) @file_get_contents($statePath), true);
        $continues = is_array($state) && ($state['signature'] ?? null) === $signature;
        $offset = $continues ? (int) $state['offset'] : 0;

        $staging = $path.DIRECTORY_SEPARATOR.'.release-tmp';

        // Si el paquete cambió, lo que quedó a medias del anterior sobra: se
        // tira antes de empezar para no publicar una mezcla de dos versiones.
        if (! $continues) {
            File::deleteDirectory($staging);
        }

        $total = $zip->numFiles;
        $until = min($offset + (int) config('deploy.chunk'), $total);
        $entries = [];

        for ($i = $offset; $i < $until; $i++) {
            $name = (string) $zip->getNameIndex($i);

            // Un zip con "../" escribiría fuera de la aplicación. El nuestro
            // nunca los trae; si aparecen, el archivo no es de confianza.
            if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/')) {
                $zip->close();

                return $this->fail("Ruta no permitida dentro del zip: {$name}");
            }

            $entries[] = $name;
        }

        if ($entries !== [] && ! $zip->extractTo($staging, $entries)) {
            $zip->close();

            return $this->fail('No se pudo escribir en el servidor: revisa los permisos de la carpeta.');
        }

        $zip->close();

        if ($until < $total) {
            file_put_contents($statePath, json_encode(['signature' => $signature, 'offset' => $until]));

            return response()->json(['done' => false, 'extraidos' => $until, 'total' => $total]);
        }

        $movidos = $this->publish($staging, $path);

        File::deleteDirectory($staging);
        @unlink($statePath);
        @unlink($archivePath);

        return response()->json([
            'done' => true,
            'extraidos' => $total,
            'total' => $total,
            'publicados' => $movidos,
            'artisan' => $this->finish(),
        ]);
    }

    /**
     * Sin token configurado la ruta no existe para nadie; con token, solo pasa
     * quien lo presenta exacto. La comparación es en tiempo constante para no
     * filtrar el token a base de medir respuestas.
     */
    private function verifyDeployToken(Request $request): void
    {
        $token = (string) config('deploy.token');

        abort_if($token === '', 404);

        if (! hash_equals($token, (string) $request->header('X-Deploy-Token'))) {
            Log::warning('Intento de despliegue con token inválido.', ['ip' => $request->ip()]);
            abort(403);
        }
    }

    /**
     * Mueve la versión ya completa de la carpeta de paso a su sitio.
     *
     * Archivo por archivo con rename(), que en Linux reemplaza de golpe: nadie
     * llega a ver un archivo a medio escribir.
     */
    private function publish(string $staging, string $path): int
    {
        if (! is_dir($staging)) {
            return 0;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($staging, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $moved = 0;

        foreach ($files as $file) {
            $target = $path.DIRECTORY_SEPARATOR.substr($file->getPathname(), strlen($staging) + 1);

            if ($file->isDir()) {
                File::ensureDirectoryExists($target);

                continue;
            }

            File::ensureDirectoryExists(dirname($target));

            // En Windows rename() no pisa el destino; en Linux sí, y es atómico.
            if (! @rename($file->getPathname(), $target)) {
                @unlink($target);

                if (! @rename($file->getPathname(), $target) && ! @copy($file->getPathname(), $target)) {
                    abort(409, "No se pudo publicar {$target}: revisa los permisos.");
                }
            }

            $moved++;
        }

        return $moved;
    }

    /**
     * Lo que el FTP no puede hacer. El orden importa: primero se tiran las
     * cachés del código viejo, luego se migra y al final se cachea el nuevo.
     *
     * @return array<string,string>
     */
    private function finish(): array
    {
        // Este proceso arrancó con la versión anterior: los módulos que trae la
        // nueva no están registrados y `migrate` no vería sus migraciones (y
        // correría las de otros módulos que dependen de ellas). Se registran
        // aquí, ya con los archivos nuevos en su sitio.
        foreach ((new ModuleServiceProvider(app()))->discoverModuleProviders() as $provider) {
            if (! app()->getProvider($provider)) {
                app()->register($provider);
            }
        }

        $output = [];

        foreach (['optimize:clear' => [], 'migrate' => ['--force' => true], 'optimize' => []] as $command => $options) {
            Artisan::call($command, $options);
            $output[$command] = trim(Artisan::output());
        }

        return $output;
    }

    private function fail(string $message): JsonResponse
    {
        return response()->json(['done' => false, 'error' => $message], 409);
    }
}
