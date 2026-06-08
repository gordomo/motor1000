<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code')->comment('Número de presupuesto, e.g. PRES-00001');
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->text('detected_fault')->nullable()->comment('Falla detectada por el cliente o mecánico');
            $table->string('status')->default('draft')->comment('draft,sent,accepted,rejected');
            $table->json('checklist')->nullable()->comment('Array de 20 puntos: id, categoria, nombre_item, estado, aclaracion');
            $table->json('items')->nullable()->comment('Repuestos y mano de obra: descripcion, tipo, cantidad, precio_unitario, total');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'vehicle_id']);
            $table->index(['tenant_id', 'customer_id']);
        });

        // Una OT puede originarse de un presupuesto aceptado (1:1)
        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('quote_id')->nullable()->after('vehicle_id')
                ->constrained('quotes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['quote_id']);
            $table->dropColumn('quote_id');
        });
        Schema::dropIfExists('quotes');
    }
};
