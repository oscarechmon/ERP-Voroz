<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Audit\Http\Controllers\Api\AuditController;

/*
| Rutas del módulo Auditoría (prefijo /api/v1). Sesión + permiso audit.view.
*/
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('audit', [AuditController::class, 'index'])->middleware('permission:audit.view');
    Route::get('audit/filters', [AuditController::class, 'filters'])->middleware('permission:audit.view');
});
