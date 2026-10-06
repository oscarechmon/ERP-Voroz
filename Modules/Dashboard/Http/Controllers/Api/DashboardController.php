<?php

declare(strict_types=1);

namespace Modules\Dashboard\Http\Controllers\Api;

use App\Core\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Modules\Dashboard\Services\DashboardService;

class DashboardController extends ApiController
{
    public function __construct(private readonly DashboardService $service)
    {
    }

    /** Métricas del dashboard (reutilizadas un minuto; una venta las renueva). */
    public function metrics(): JsonResponse
    {
        return $this->ok($this->service->cached());
    }
}
