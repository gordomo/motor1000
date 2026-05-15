<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->comment('oil_change,brake_inspection,tire_rotation,alignment,checkup,custom');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('trigger_type')->default('date')->comment('date,mileage,months_since_last');
            $table->timestamp('due_at')->nullable();
            $table->unsignedInteger('due_mileage')->nullable();
            $table->string('status')->default('pending')
                ->comment('pending,sent,dismissed,completed');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
