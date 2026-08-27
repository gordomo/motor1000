<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué mecánico hizo cada cambio de estado.
 *
 * El historial ya guarda user_id, pero en el totem del taller la sesión va a ser
 * compartida: user_id sería siempre el mismo. Cuando el mecánico toma la orden
 * elige su nombre, y ese dato queda acá.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_order_status_history', function (Blueprint $table) {
            if (! Schema::hasColumn('work_order_status_history', 'mechanic_id')) {
                $table->foreignId('mechanic_id')->nullable()->after('user_id')
                    ->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_order_status_history', function (Blueprint $table) {
            if (Schema::hasColumn('work_order_status_history', 'mechanic_id')) {
                $table->dropConstrainedForeignId('mechanic_id');
            }
        });
    }
};
