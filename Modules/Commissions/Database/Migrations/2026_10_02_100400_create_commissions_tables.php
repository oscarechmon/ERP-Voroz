<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comisiones del personal.
 *
 * - `commission_rules`: cuánto comisiona (porcentaje o monto fijo) un
 *   empleado, un servicio, ambos o el centro en general (ambos vacíos).
 * - `commissions`: la comisión generada por cada atención, con el monto ya
 *   calculado: si mañana cambia la regla, el histórico no se altera.
 *   La FK a attendances la agrega el módulo Atenciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('products')->cascadeOnDelete();
            $table->string('type', 20);
            $table->decimal('value', 10, 2);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['employee_id', 'service_id']);
        });

        Schema::create('commissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedBigInteger('attendance_id')->nullable()->unique();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('products')->nullOnDelete();
            $table->decimal('base_amount', 12, 2);
            $table->string('type', 20);
            $table->decimal('value', 10, 2);
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('pending')->index();
            $table->date('generated_at')->index();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('commission_rules');
    }
};
