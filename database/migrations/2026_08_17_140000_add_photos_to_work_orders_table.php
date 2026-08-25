<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedido 3: fotos en la orden de trabajo.
 *
 * El caso de uso es documentar el estado del vehículo al ingreso (rayones,
 * golpes) para evitar reclamos posteriores, así que se separan las fotos de
 * ingreso de las de entrega.
 *
 * Se guardan como JSON con las rutas en el disco público, igual que ya se hace
 * con el checklist. No se usa spatie/media-library (está en el composer.json
 * pero sin publicar ni migrar) para no arrastrar tres tablas más por esto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('work_orders', 'photos_in')) {
                $table->json('photos_in')->nullable()->after('checklist')
                    ->comment('Fotos del estado del vehículo al ingreso');
            }

            if (! Schema::hasColumn('work_orders', 'photos_out')) {
                $table->json('photos_out')->nullable()->after('photos_in')
                    ->comment('Fotos del vehículo al momento de la entrega');
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            foreach (['photos_in', 'photos_out'] as $column) {
                if (Schema::hasColumn('work_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
