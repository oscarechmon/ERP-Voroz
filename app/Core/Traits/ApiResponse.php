<?php

declare(strict_types=1);

namespace App\Core\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Estandariza el formato de todas las respuestas JSON de la API.
 * Un contrato de respuesta uniforme facilita el consumo desde el frontend (Axios).
 */
trait ApiResponse
{
    protected function ok(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function created(mixed $data = null, string $message = 'Recurso creado correctamente'): JsonResponse
    {
        return $this->ok($data, $message, 201);
    }

    protected function noContent(string $message = 'Operación realizada'): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message], 200);
    }

    protected function error(string $message, int $status = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
