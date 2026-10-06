<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Envíos de la tienda online: el delivery en Lima y el envío a provincia por
 * agencia (Shalom) tienen cada uno su costo, que se cambia aquí y la web lee.
 *
 * Un envío a provincia lleva además el documento de quien recoge en la
 * agencia (DNI o CE), su departamento y provincia y la agencia de destino.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_store_settings', function (Blueprint $table): void {
            $table->string('key', 60)->primary();
            $table->string('value')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('online_store_settings')->insert([
            ['key' => 'delivery_enabled', 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'delivery_fee', 'value' => '10.00', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'province_enabled', 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'province_fee', 'value' => '12.00', 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::table('online_orders', function (Blueprint $table): void {
            $table->string('document_type', 10)->nullable()->after('recipient_name');
            $table->string('document_number', 20)->nullable()->after('document_type');
            $table->string('department', 100)->nullable()->after('reference');
            $table->string('province', 100)->nullable()->after('department');
            $table->string('agency')->nullable()->after('province');
        });
    }

    public function down(): void
    {
        Schema::table('online_orders', function (Blueprint $table): void {
            $table->dropColumn(['document_type', 'document_number', 'department', 'province', 'agency']);
        });

        Schema::dropIfExists('online_store_settings');
    }
};
