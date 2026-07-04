<?php

declare(strict_types=1);

namespace App\Core\Repositories;

use App\Core\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Implementación base de los repositorios.
 *
 * Centraliza el CRUD, la paginación server-side, la búsqueda global, el filtrado
 * por columnas y el ordenamiento para que ningún repositorio concreto repita esa
 * lógica (DRY). Cada repositorio hijo sólo declara el Modelo y sus columnas
 * buscables/filtrables/ordenables.
 */
abstract class BaseRepository implements RepositoryInterface
{
    protected Model $model;

    /** Columnas contra las que se ejecuta la búsqueda global (parámetro `search`). */
    protected array $searchable = [];

    /** Columnas permitidas para filtrar exactamente (whitelist anti-inyección). */
    protected array $filterable = [];

    /** Columnas permitidas para ordenar (whitelist). */
    protected array $sortable = ['id'];

    /** Relaciones a cargar por defecto (evita N+1). */
    protected array $defaultWith = [];

    abstract protected function model(): Model;

    public function __construct()
    {
        $this->model = $this->model();
    }

    public function query(): Builder
    {
        return $this->model->newQuery()->with($this->defaultWith);
    }

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? $perPage), 1), 100);

        return $this->applyFilters($this->query(), $filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(array $filters = []): Collection
    {
        return $this->applyFilters($this->query(), $filters)->get();
    }

    public function findOrFail(int|string $id, array $with = []): Model
    {
        return $this->query()->with($with)->findOrFail($id);
    }

    public function find(int|string $id, array $with = []): ?Model
    {
        return $this->query()->with($with)->find($id);
    }

    public function findBy(string $column, mixed $value, array $with = []): ?Model
    {
        return $this->query()->with($with)->where($column, $value)->first();
    }

    public function create(array $data): Model
    {
        return $this->model->newInstance()->create($data);
    }

    public function update(int|string $id, array $data): Model
    {
        $record = $this->findOrFail($id);
        $record->update($data);

        return $record->refresh();
    }

    public function delete(int|string $id): bool
    {
        return (bool) $this->findOrFail($id)->delete();
    }

    public function deleteMany(array $ids): int
    {
        return $this->model->newQuery()->whereIn($this->model->getKeyName(), $ids)->delete();
    }

    /**
     * Aplica búsqueda global, filtros exactos y ordenamiento sobre el query.
     * Todo pasa por whitelists ($searchable/$filterable/$sortable) para evitar
     * inyección de columnas arbitrarias desde el cliente.
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        // Búsqueda global tipo "buscador" de las tablas del frontend.
        if (! empty($filters['search']) && ! empty($this->searchable)) {
            $term = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($term): void {
                foreach ($this->searchable as $column) {
                    $q->orWhere($column, 'like', "%{$term}%");
                }
            });
        }

        // Filtros exactos por columna (chips / selects de la tabla).
        foreach ($this->filterable as $column) {
            if (array_key_exists($column, $filters) && $filters[$column] !== '' && $filters[$column] !== null) {
                $value = $filters[$column];
                is_array($value)
                    ? $query->whereIn($column, $value)
                    : $query->where($column, $value);
            }
        }

        // Sólo activos / incluir eliminados (SoftDeletes) según flag.
        if (! empty($filters['trashed']) && method_exists($this->model, 'trashed')) {
            $filters['trashed'] === 'only'
                ? $query->onlyTrashed()
                : $query->withTrashed();
        }

        // Ordenamiento server-side (columna + dirección) validado por whitelist.
        $sort = $filters['sort_by'] ?? 'id';
        $direction = strtolower((string) ($filters['sort_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        if (in_array($sort, $this->sortable, true)) {
            $query->orderBy($sort, $direction);
        }

        return $query;
    }
}
