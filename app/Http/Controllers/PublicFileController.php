<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use League\Flysystem\PathTraversalDetected;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Las fotos subidas (disco `public`: productos, servicios, logo, avatares).
 *
 * Normalmente ni se llega aquí: con el enlace public/storage → storage/app/public
 * (`php artisan storage:link`, que el despliegue crea si falta) Apache entrega
 * el archivo directo. Si el enlace no existe o el hosting lo pierde, esta ruta
 * las sirve igual, en vez de un 404: la web pública muestra las fotos del
 * catálogo desde aquí y no puede quedarse con imágenes rotas.
 *
 * Cada foto se guarda con un nombre único (img_<único>.webp, logo_<único>.webp):
 * si cambia, cambia la URL, así que el navegador puede guardarla para siempre.
 */
class PublicFileController extends Controller
{
    public function __invoke(string $path): BinaryFileResponse
    {
        $disk = Storage::disk('public');

        try {
            abort_unless($disk->fileExists($path), 404);
        } catch (PathTraversalDetected) {
            abort(404);
        }

        return response()->file($disk->path($path), [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
