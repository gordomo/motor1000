<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos del nuevo flujo de órdenes de trabajo definido con el cliente:
 *
 * - blocked_reason / blocked_at: el mecánico no puede arrancar porque falta algo
 *   (repuestos, herramientas). La orden queda marcada sin cambiar de estado, así
 *   no hace falta reponer la columna "Esperando piezas" que se había quitado.
 * - work_type: tipo de trabajo (normal, revisión gratuita, revisión paga...). Lo
 *   hereda del presupuesto.
 * - work_performed: qué se hizo, que el mecánico completa al cerrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('work_orders', 'blocked_reason')) {
                $table->text('blocked_reason')->nullable()->after('status')
                    ->comment('Por qué no se puede empezar a trabajar');
            }

            if (! Schema::hasColumn('work_orders', 'blocked_at')) {
                $table->timestamp('blocked_at')->nullable()->after('blocked_reason');
            }

            if (! Schema::hasColumn('work_orders', 'work_type')) {
                $table->string('work_type')->nullable()->after('priority')
                    ->comment('Tipo de trabajo heredado del presupuesto');
            }

            if (! Schema::hasColumn('work_orders', 'work_performed')) {
                $table->text('work_performed')->nullable()->after('diagnosis')
                    ->comment('Trabajo realizado, lo carga el mecánico al completar');
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            foreach (['blocked_reason', 'blocked_at', 'work_type', 'work_performed'] as $column) {
                if (Schema::hasColumn('work_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
