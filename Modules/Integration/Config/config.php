<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Integración con la web (sin_excusas)
|--------------------------------------------------------------------------
| El sistema es dueño del catálogo y del stock; la web guarda una copia para
| mostrarla y le pide al sistema que registre lo que vende. Ver DEPLOY.md.
*/

return [
    // Secreto compartido con la web: ella lo presenta para usar esta API y el
    // sistema lo presenta al avisarle cambios. Vacío = integración apagada.
    'token' => env('INTEGRATION_TOKEN'),

    // Raíz de la web. Los cambios del catálogo se avisan a {web_url}/erp/catalogo.
    'web_url' => env('INTEGRATION_WEB_URL'),
];
