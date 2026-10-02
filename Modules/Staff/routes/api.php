<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Staff\Http\Controllers\Api\EmployeeController;

/*
| Rutas del módulo Personal (prefijo /api/v1). Sesión + permisos por acción.
*/
Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('employees', EmployeeController::class)->only(['index', 'store', 'show', 'update', 'destroy'])
        ->middlewareFor('index', 'permission:employees.view')
        ->middlewareFor('show', 'permission:employees.view')
        ->middlewareFor('store', 'permission:employees.create')
        ->middlewareFor('update', 'permission:employees.edit')
        ->middlewareFor('destroy', 'permission:employees.delete');
});
