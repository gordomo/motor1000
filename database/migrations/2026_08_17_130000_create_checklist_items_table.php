<?php

use App\Models\ChecklistItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pedidos 2 y 17: los puntos del checklist pasan a ser configurables por taller.
 *
 * Antes eran 20 puntos fijos escritos en Quote::defaultChecklist(), iguales para
 * todos los talleres y solo modificables con un deploy. El cliente pidió poder
 * agregar sus propios ítems a revisar.
 *
 * Cada taller existente arranca con exactamente los mismos 20 puntos que tenía,
 * así nadie ve un cambio al desplegar. Los presupuestos ya guardados no se tocan:
 * su checklist vive como snapshot en quotes.checklist (JSON) y sigue igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('categoria');
            $table->string('nombre_item');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'sort_order']);
        });

        // Sembrar el catálogo actual para cada taller ya existente.
        $now = now();

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $rows = [];

            foreach (ChecklistItem::DEFAULT_CATALOG as $i => $item) {
                $rows[] = [
                    'tenant_id'   => $tenantId,
                    'categoria'   => $item['categoria'],
                    'nombre_item' => $item['nombre_item'],
                    'sort_order'  => $i + 1,
                    'is_active'   => true,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }

            DB::table('checklist_items')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_items');
    }
};
