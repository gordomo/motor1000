<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('license_plate');
            $table->string('brand');
            $table->string('model');
            $table->year('year');
            $table->string('color')->nullable();
            $table->string('vin')->nullable()->comment('Chassis number');
            $table->unsignedInteger('mileage')->default(0)->comment('km');
            $table->string('fuel_type')->default('gasoline')
                ->comment('gasoline,ethanol,flex,diesel,electric,hybrid');
            $table->string('transmission')->default('manual')
                ->comment('manual,automatic,cvt');
            $table->string('engine')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_service_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'license_plate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
