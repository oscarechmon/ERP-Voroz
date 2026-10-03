<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Si el paquete se muestra en la web. Se copia a su producto como el nombre y
 * el precio. Vacío: la web sigue con lo que tenía antes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            $table->boolean('web_published')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            $table->dropColumn('web_published');
        });
    }
};
