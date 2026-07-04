<?php

declare(strict_types=1);

namespace App\Core\DTO;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Objeto de transferencia de datos base.
 *
 * Los DTO transportan datos ya validados desde la capa HTTP (FormRequest) hacia
 * los Services, desacoplando la lógica de negocio de la forma de la petición.
 * Cada DTO concreto define sus propiedades tipadas y un `fromRequest()`.
 */
abstract class BaseDTO
{
    /** Construye el DTO a partir de un FormRequest ya validado. */
    abstract public static function fromRequest(FormRequest $request): static;

    /** Convierte el DTO a array para persistencia, ignorando valores null. */
    public function toArray(): array
    {
        return array_filter(
            get_object_vars($this),
            static fn ($value) => $value !== null,
        );
    }

    /** Igual que toArray pero conservando nulls (para updates parciales explícitos). */
    public function toArrayWithNulls(): array
    {
        return get_object_vars($this);
    }
}
