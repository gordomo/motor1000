<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cobros de las órdenes de trabajo.
 *
 * El cliente definió que "orden entregada = cobro realizado", pero también que al
 * aprobar un presupuesto se puede registrar un adelanto. Con un solo campo en la
 * orden esos dos momentos no se pueden representar, y el dashboard mostraría toda
 * la plata en la fecha de entrega.
 *
 * Cada fila es un ingreso de dinero real, con su fecha, su forma de pago y quién
 * lo registró. El dashboard suma por fecha de cobro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('saldo')->comment('adelanto,saldo,ajuste');
            $table->decimal('amount', 12, 2);
            $table->string('method')->comment('efectivo,transferencia,debito,credito,mercadopago,cheque,otro');
            $table->timestamp('paid_at');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()
                ->comment('Quién registró el cobro');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'paid_at']);
            $table->index(['tenant_id', 'work_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
