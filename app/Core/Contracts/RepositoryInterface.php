<?php

declare(strict_types=1);

namespace App\Core\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Contrato base para todos los repositorios del sistema.
 *
 * Abstrae el acceso a datos (Eloquent) para que los Services dependan de esta
 * interfaz y no de una implementación concreta (Principio de Inversión de
 * Dependencias — la "D" de SOLID).
 */
interface RepositoryInterface
{
    /** Devuelve una consulta paginada aplicando filtros, búsqueda y ordenamiento. */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /** Devuelve todos los registros (uso interno / exportaciones). */
    public function all(array $filters = []): Collection;

    /** Busca un registro por id o lanza ModelNotFoundException. */
    public function findOrFail(int|string $id, array $with = []): Model;

    /** Busca un registro por id o devuelve null. */
    public function find(int|string $id, array $with = []): ?Model;

    /** Busca un registro por una columna arbitraria. */
    public function findBy(string $column, mixed $value, array $with = []): ?Model;

    /** Crea un nuevo registro. */
    public function create(array $data): Model;

    /** Actualiza un registro existente y lo devuelve fresco. */
    public function update(int|string $id, array $data): Model;

    /** Elimina (soft delete si el modelo lo soporta) un registro. */
    public function delete(int|string $id): bool;

    /** Elimina múltiples registros por ids (acciones masivas de las tablas). */
    public function deleteMany(array $ids): int;

    /** Devuelve el query builder base para composiciones avanzadas en el Service. */
    public function query();
}
