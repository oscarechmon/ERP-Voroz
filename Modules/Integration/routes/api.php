<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Integration\Http\Controllers\IntegrationController;
use Modules\Integration\Http\Middleware\VerifyIntegrationToken;

/*
| API de integración con la web (prefijo /api/v1/integration). La usa el
| servidor de sin_excusas con el token compartido, no una sesión.
*/
Route::middleware([VerifyIntegrationToken::class, 'throttle:240,1'])
    ->prefix('integration')
    ->controller(IntegrationController::class)
    ->group(function (): void {
        Route::get('catalog', 'catalog');
        Route::post('products', 'storeProduct');
        Route::post('sales', 'storeSale');
        Route::post('sales/{reference}/cancel', 'cancelSale');
        Route::post('consumptions', 'storeConsumption');
    });
