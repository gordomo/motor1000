<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('document')->nullable()->comment('CPF/CNPJ');
            $table->string('document_type')->default('cpf');
            $table->date('birthday')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip')->nullable();
            $table->string('status')->default('active')->comment('active,inactive,vip,prospect');
            $table->text('notes')->nullable();
            $table->json('tags')->nullable();
            $table->timestamp('last_visit_at')->nullable();
            $table->boolean('whatsapp_opted_in')->default(true);
            $table->boolean('email_opted_in')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'last_visit_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
