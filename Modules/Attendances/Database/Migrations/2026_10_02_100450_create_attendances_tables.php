<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atenciones: el servicio efectivamente realizado a un cliente, con los insumos
 * que se usaron. `service_supplies` dice qué insumos usa normalmente cada
 * servicio (se proponen al registrar la atención).
 *
 * También enlaza con sus atenciones las sesiones de paquete y las comisiones,
 * cuyas tablas se crearon antes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('customer_package_id')->nullable()->constrained('customer_packages')->nullOnDelete();
            $table->unsignedInteger('session_number')->nullable();
            $table->date('attended_at')->index();
            $table->text('observations')->nullable();
            $table->text('measurements')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'attended_at']);
        });

        Schema::create('attendance_supplies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendances')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->timestamps();

            $table->unique(['attendance_id', 'product_id']);
        });

        Schema::create('service_supplies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('supply_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('default_quantity', 12, 2)->default(1);
            $table->timestamps();

            $table->unique(['service_id', 'supply_id']);
        });

        // SQLite (pruebas) no agrega llaves foráneas a tablas existentes.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('customer_package_sessions', function (Blueprint $table): void {
                $table->foreign('attendance_id')->references('id')->on('attendances')->nullOnDelete();
            });
            Schema::table('commissions', function (Blueprint $table): void {
                $table->foreign('attendance_id')->references('id')->on('attendances')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('commissions', fn (Blueprint $table) => $table->dropForeign(['attendance_id']));
            Schema::table('customer_package_sessions', fn (Blueprint $table) => $table->dropForeign(['attendance_id']));
        }

        Schema::dropIfExists('service_supplies');
        Schema::dropIfExists('attendance_supplies');
        Schema::dropIfExists('attendances');
    }
};
