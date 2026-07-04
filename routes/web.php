<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas Web
|--------------------------------------------------------------------------
| La aplicación es una SPA. Blade sólo entrega el punto de entrada; el
| enrutamiento real lo maneja Vue Router en el cliente. Esta ruta "catch-all"
| devuelve la misma vista para cualquier URL que no sea de la API o de assets,
| permitiendo recargar el navegador en cualquier ruta del SPA.
*/
Route::get('/{any?}', fn () => view('app'))
    ->where('any', '^(?!api|sanctum|storage|build).*$')
    ->name('spa');
