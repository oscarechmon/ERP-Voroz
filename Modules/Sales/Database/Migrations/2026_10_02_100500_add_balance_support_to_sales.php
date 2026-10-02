<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ventas con saldo: una venta puede quedar con pago parcial y cobrarse después.
 *
 * Cada pago guarda quién lo cobró y cuándo, porque ya no coincide con el
 * momento de la venta: así la caja suma el efectivo del turno en que entró.
 * La línea guarda quién atendió (especialista del servicio).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_payments', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('reference')->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable()->after('user_id')->index();
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->foreignId('employee_id')->nullable()->after('product_id')->constrained('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('employee_id');
        });

        Schema::table('sale_payments', function (Blueprint $table): void {
            $table->dropIndex(['paid_at']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('paid_at');
        });
    }
};
