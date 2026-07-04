<?php

declare(strict_types=1);

namespace Modules\Audit\Http\Controllers\Api;

use App\Core\Http\Controllers\ApiController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Audit\Http\Resources\AuditResource;
use OwenIt\Auditing\Models\Audit;

/**
 * Consulta del registro de auditoría (quién hizo qué, cuándo, desde dónde, con
 * los valores antes/después). Los datos los captura owen-it/laravel-auditing.
 */
class AuditController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $audits = Audit::query()
            ->with('user:id,name')
            ->when($request->filled('event'), fn (Builder $q) => $q->where('event', $request->string('event')))
            ->when($request->filled('model'), fn (Builder $q) => $q->where('auditable_type', 'like', '%' . $request->string('model')))
            ->when($request->filled('user_id'), fn (Builder $q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('from'), fn (Builder $q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return $this->ok(AuditResource::collection($audits)->response()->getData(true));
    }

    /** Modelos y eventos disponibles para poblar los filtros del frontend. */
    public function filters(): JsonResponse
    {
        $models = Audit::query()
            ->select('auditable_type')
            ->distinct()
            ->pluck('auditable_type')
            ->map(fn ($t) => ['value' => class_basename($t), 'label' => class_basename($t)])
            ->values();

        return $this->ok([
            'events' => [
                ['value' => 'created', 'label' => 'Creación'],
                ['value' => 'updated', 'label' => 'Actualización'],
                ['value' => 'deleted', 'label' => 'Eliminación'],
            ],
            'models' => $models,
        ]);
    }
}
