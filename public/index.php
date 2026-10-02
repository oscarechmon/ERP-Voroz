<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// El .htaccess de la raíz reenvía cada petición a public/ sin que se vea en la
// URL. Si la app se sirve desde una carpeta (dominio.com/carpeta), Apache
// informa SCRIPT_NAME=/carpeta/public/index.php para /carpeta/ventas y Laravel,
// al no ver "/public" en la URL, cree que vive en la raíz del dominio: no
// reconoce ninguna ruta y genera los enlaces sin /carpeta. Quitando "/public"
// recupera su prefijo real. En la raíz de un (sub)dominio, con artisan serve o
// con /public en la URL no cambia nada.
$script = $_SERVER['SCRIPT_NAME'] ?? '';
if (str_ends_with($script, '/public/index.php')
    && ! str_starts_with($_SERVER['REQUEST_URI'] ?? '', substr($script, 0, -strlen('index.php')))) {
    $_SERVER['SCRIPT_NAME'] = substr($script, 0, -strlen('/public/index.php')).'/index.php';
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
