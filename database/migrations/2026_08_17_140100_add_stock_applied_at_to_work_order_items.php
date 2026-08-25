<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedido 5: el consumo de repuestos descuenta stock al completar la orden.
 *
 * Esta marca es la que hace la operación idempotente: si la orden vuelve a
 * Completado (o alguien reintenta), el ítem ya descontado no se descuenta otra
 * vez. Sin esto, un doble clic se come el inventario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('work_order_items', 'stock_applied_at')) {
                $table->timestamp('stock_applied_at')->nullable()->after('inventory_item_id')
                    ->comment('Cuándo se descontó del inventario. Null = todavía no impactó.');
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('work_order_items', 'stock_applied_at')) {
                $table->dropColumn('stock_applied_at');
            }
        });
    }
};
