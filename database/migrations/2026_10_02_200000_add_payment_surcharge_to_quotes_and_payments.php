<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formas de pago con recargo (tarjeta: costo de facturación + costo financiero
 * por cuotas, configurados en Recargos con tarjeta).
 *
 * - quotes: la forma de pago que eligió el cliente y el recargo calculado al
 *   presupuestar (queda fijo aunque después cambien los porcentajes).
 * - payments: cuotas y cuánto del cobro fue recargo. `amount` sigue siendo lo
 *   que pagó el cliente; el saldo de la orden se descuenta sin el recargo.
 *
 * Aditiva: columnas nuevas, nullable o en 0. No toca datos existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('total');
            $table->unsignedTinyInteger('installments')->nullable()->after('payment_method');
            $table->decimal('surcharge', 12, 2)->default(0)->after('installments');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedTinyInteger('installments')->nullable()->after('method');
            $table->decimal('surcharge', 12, 2)->default(0)->after('installments');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'installments', 'surcharge']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['installments', 'surcharge']);
        });
    }
};
