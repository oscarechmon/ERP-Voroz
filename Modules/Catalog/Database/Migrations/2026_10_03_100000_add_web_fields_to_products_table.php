<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que la web pública muestra de cada ítem también se administra aquí: si se
 * publica en la web y, en un servicio, cuánto dura. La imagen y la descripción
 * ya estaban en el producto.
 *
 * `web_published` vacío significa "todavía no se decidió aquí": la web sigue
 * con lo que tenía antes de pasar su catálogo al sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('web_published')->nullable()->after('is_active');
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('web_published');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['web_published', 'duration_minutes']);
        });
    }
};
