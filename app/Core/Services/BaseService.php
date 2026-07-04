<?php

declare(strict_types=1);

namespace App\Core\Services;

use App\Core\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio base con las operaciones CRUD estándar delegadas al repositorio.
 *
 * Los servicios concretos heredan esto y añaden reglas de negocio (transacciones,
 * eventos, validaciones de dominio). Los controllers dependen de servicios, nunca
 * de repositorios o modelos directamente.
 */
abstract class BaseService
{
    public function __construct(protected RepositoryInterface $repository)
    {
    }

    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->repository->paginate($filters);
    }

    public function all(array $filters = []): Collection
    {
        return $this->repository->all($filters);
    }

    public function find(int|string $id, array $with = []): Model
    {
        return $this->repository->findOrFail($id, $with);
    }

    public function create(array $data): Model
    {
        return $this->repository->create($data);
    }

    public function update(int|string $id, array $data): Model
    {
        return $this->repository->update($id, $data);
    }

    public function delete(int|string $id): bool
    {
        return $this->repository->delete($id);
    }

    public function bulkDelete(array $ids): int
    {
        return $this->repository->deleteMany($ids);
    }
}
