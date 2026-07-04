<?php

declare(strict_types=1);

namespace Modules\Dashboard\Http\Controllers\Api;

use App\Core\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Modules\Dashboard\Services\DashboardService;

class DashboardController extends ApiController
{
    public function __construct(private readonly DashboardService $service)
    {
    }

    /** Métricas del dashboard (cacheadas 60s para aligerar recargas frecuentes). */
    public function metrics(): JsonResponse
    {
        $data = Cache::remember('dashboard.metrics.' . auth()->id(), 60, fn () => $this->service->metrics());

        return $this->ok($data);
    }
}
