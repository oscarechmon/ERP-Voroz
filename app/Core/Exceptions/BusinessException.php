<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Excepción de reglas de negocio (stock insuficiente, caja cerrada, correlativo
 * agotado, etc.). Se renderiza automáticamente como una respuesta JSON coherente,
 * evitando fugas de detalles internos al cliente.
 */
class BusinessException extends Exception
{
    public function __construct(
        string $message = 'No se pudo completar la operación.',
        protected int $status = 422,
        protected mixed $errors = null,
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'errors' => $this->errors,
        ], $this->status);
    }

    public static function make(string $message, int $status = 422): self
    {
        return new self($message, $status);
    }
}
