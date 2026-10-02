<?php

declare(strict_types=1);

namespace Modules\Integration\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puerta de la API de integración: la usa el servidor de la web, no una
 * persona, así que no hay sesión ni permisos sino un secreto compartido.
 *
 * Sin token configurado la API no existe para nadie; con token, solo pasa
 * quien lo presenta exacto. La comparación es en tiempo constante.
 */
class VerifyIntegrationToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('integration.token');

        abort_if($token === '', 404);

        if (! hash_equals($token, (string) $request->header('X-Integration-Token'))) {
            Log::warning('Llamada a la integración con token inválido.', ['ip' => $request->ip()]);
            abort(403);
        }

        return $next($request);
    }
}
