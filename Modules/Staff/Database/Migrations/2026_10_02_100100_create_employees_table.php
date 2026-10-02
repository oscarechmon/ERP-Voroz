<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personal del centro. No todo empleado entra al sistema: el enlace con un
 * usuario es opcional (una especialista puede atender sin tener cuenta).
 * `employee_service` dice qué servicios atiende cada uno (la agenda lo usa).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('position', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('doc_number', 20)->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('employee_service', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_service');
        Schema::dropIfExists('employees');
    }
};
