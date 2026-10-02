<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paquetes de sesiones.
 *
 * - `packages`: el catálogo (N sesiones de ciertos servicios, con vigencia).
 *   Cada paquete tiene su producto de tipo `package`, que es lo que vende el POS.
 * - `customer_packages`: lo que compró cada cliente. Nombre, precio y sesiones
 *   se copian al venderlo para que el histórico sobreviva a cambios del catálogo.
 * - `customer_package_sessions`: una fila por sesión consumida. El índice único
 *   (paquete, número de sesión) impide que un doble clic consuma dos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->nullable()->unique()->constrained('products')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('total_sessions');
            $table->unsignedInteger('validity_days')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('package_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['package_id', 'product_id']);
        });

        Schema::create('customer_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->string('package_name');
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('total_sessions');
            $table->unsignedInteger('used_sessions')->default(0);
            $table->date('purchased_at');
            $table->date('expires_at')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });

        Schema::create('customer_package_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_package_id')->constrained('customer_packages')->cascadeOnDelete();
            // La FK a attendances la agrega el módulo Atenciones (se crea después).
            $table->unsignedBigInteger('attendance_id')->nullable()->index();
            $table->unsignedInteger('session_number');
            $table->timestamp('consumed_at');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Nombre corto: el generado pasa el límite de 64 caracteres de MySQL.
            $table->unique(['customer_package_id', 'session_number'], 'cps_package_session_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_package_sessions');
        Schema::dropIfExists('customer_packages');
        Schema::dropIfExists('package_services');
        Schema::dropIfExists('packages');
    }
};
