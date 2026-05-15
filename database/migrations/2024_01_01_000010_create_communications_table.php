<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reminder_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel')->comment('whatsapp,email,sms');
            $table->string('direction')->default('outbound')->comment('outbound,inbound');
            $table->string('to');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('template')->nullable();
            $table->string('status')->default('pending')
                ->comment('pending,sent,delivered,read,failed');
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'channel', 'status']);
        });

        Schema::create('communication_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('channel')->comment('whatsapp,email,sms');
            $table->string('event')->nullable()->comment('vehicle_ready,appointment_reminder,etc');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->json('variables')->nullable()->comment('Available template variables');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_templates');
        Schema::dropIfExists('communications');
    }
};
