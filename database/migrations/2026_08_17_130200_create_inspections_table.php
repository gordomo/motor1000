<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedido 17: módulo de Revisiones. Igual que un presupuesto pero sin precios:
 * datos del cliente, del auto, el checklist editable y notas. Sirve para
 * estandarizar el protocolo de revisión, se exporta a PDF y queda en el historial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code')->comment('REV-00001');
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('mileage')->nullable();
            $table->json('checklist')->nullable()
                ->comment('Snapshot de los puntos revisados: id_punto, categoria, nombre_item, estado, aclaracion');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'vehicle_id']);
            $table->index(['tenant_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
