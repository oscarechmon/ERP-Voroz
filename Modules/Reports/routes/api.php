<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Reports\Http\Controllers\Api\ReportController;

/*
| Rutas del módulo Reportes (prefijo /api/v1). Sesión + permiso.
| Un mismo endpoint devuelve JSON o exporta (?export=xlsx|csv|pdf).
*/
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('reports/{type}', [ReportController::class, 'show'])->middleware('permission:reports.view');
});
