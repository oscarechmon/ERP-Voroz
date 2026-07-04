<?php

declare(strict_types=1);

namespace App\Core\Http\Controllers;

use App\Core\Traits\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller;

/**
 * Controlador base para toda la API. Aporta el formato de respuesta uniforme
 * (ApiResponse) y las utilidades de autorización (Policies/Gates).
 */
abstract class ApiController extends Controller
{
    use ApiResponse;
    use AuthorizesRequests;
}
