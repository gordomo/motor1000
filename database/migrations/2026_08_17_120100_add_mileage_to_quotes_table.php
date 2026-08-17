<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedido 11: el kilometraje pasa a ser obligatorio al presupuestar.
 *
 * La columna va nullable aunque el formulario la exija: los 55 presupuestos que ya
 * existen en prod no lo tienen y no se puede inventar el dato. La obligatoriedad se
 * aplica en el form (de acá en adelante), no en el schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            if (! Schema::hasColumn('quotes', 'mileage')) {
                $table->unsignedInteger('mileage')
                    ->nullable()
                    ->after('vehicle_id')
                    ->comment('KM del vehículo al momento de presupuestar');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            if (Schema::hasColumn('quotes', 'mileage')) {
                $table->dropColumn('mileage');
            }
        });
    }
};
