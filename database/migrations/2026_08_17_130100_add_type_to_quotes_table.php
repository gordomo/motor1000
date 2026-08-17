<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedido 2: dos tipos de presupuesto, con checklist obligatorio y sin checklist.
 *
 * Los 55 presupuestos existentes quedan como 'con_checklist', que es lo que eran:
 * el checklist era obligatorio para todos. No se modifica ningún dato cargado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            if (! Schema::hasColumn('quotes', 'type')) {
                $table->string('type')
                    ->default('con_checklist')
                    ->after('status')
                    ->comment('con_checklist,sin_checklist');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            if (Schema::hasColumn('quotes', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
