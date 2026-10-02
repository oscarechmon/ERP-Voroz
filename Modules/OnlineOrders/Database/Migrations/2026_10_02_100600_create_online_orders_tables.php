<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedidos de la tienda online. Nacen y se cobran en la web (Izipay); desde que
 * están pagados, su seguimiento (preparación, envío, entrega, anulación) se
 * gestiona aquí y se avisa a la web para que el cliente lo vea.
 *
 * `code` (W-000123) es la llave compartida con la web y la referencia externa
 * de la venta que genera el pedido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('web_id')->nullable()->unique();
            $table->string('code', 30)->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->string('status', 30)->index();
            $table->string('fulfillment', 20);
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('recipient_name');
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('district', 100)->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('gateway', 30)->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('ordered_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('online_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('online_order_id')->constrained('online_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('item_type', 20)->default('product');
            $table->string('name');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('quantity', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });

        Schema::create('online_order_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('online_order_id')->constrained('online_orders')->cascadeOnDelete();
            $table->string('status', 30);
            $table->text('note')->nullable();
            $table->boolean('internal')->default(false);
            // web = vino en el pedido desde la web; sistema = cambio hecho aquí.
            $table->string('source', 10)->default('sistema');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->timestamp('happened_at');
            $table->timestamps();

            $table->index(['online_order_id', 'happened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_order_status_histories');
        Schema::dropIfExists('online_order_items');
        Schema::dropIfExists('online_orders');
    }
};
