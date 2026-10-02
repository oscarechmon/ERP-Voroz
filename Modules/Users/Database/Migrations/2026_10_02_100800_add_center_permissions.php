<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Users\Database\Seeders\RolePermissionSeeder;

/**
 * Permisos de los módulos del centro (personal, paquetes, agenda, atenciones,
 * comisiones, pedidos online y cobro de saldos) y los roles Recepción y
 * Especialista. Va como migración para que el despliegue los agregue solo;
 * el seeder es idempotente (crea lo que falta y fija los roles base).
 */
return new class extends Migration
{
    public function up(): void
    {
        (new RolePermissionSeeder)->run();
    }

    public function down(): void
    {
        // Los permisos quedan: quitarlos dejaría usuarios sin acceso a lo que ya usan.
    }
};
