<?php

/*
|--------------------------------------------------------------------------
| Publicación desde GitHub Actions
|--------------------------------------------------------------------------
| El despliegue es por FTP, que no puede descomprimir ni ejecutar comandos.
| Con este token, GitHub Actions llama a POST /deploy/release al terminar de
| subir el paquete y el servidor lo descomprime, migra y regenera las cachés.
| Sin token configurado la ruta responde 404.
*/

return [
    'token' => env('DEPLOY_TOKEN'),

    // Nombre del paquete que sube GitHub Actions por FTP y carpeta donde se
    // descomprime (la raíz del proyecto, salvo en las pruebas).
    'archive' => env('DEPLOY_ARCHIVE', 'release.zip'),
    'path' => env('DEPLOY_PATH'),

    // Entradas del zip por petición. Bajarlo si el hosting corta por tiempo.
    'chunk' => (int) env('DEPLOY_CHUNK', 1200),
];
