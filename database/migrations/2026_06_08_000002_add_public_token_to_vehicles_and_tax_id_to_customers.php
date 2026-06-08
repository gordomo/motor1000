<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Agregar public_token a vehicles para QR
        Schema::table('vehicles', function (Blueprint $table) {
            $table->uuid('public_token')->nullable()->unique()->after('last_service_at');
        });

        // Poblar public_token en registros existentes
        \DB::table('vehicles')->whereNull('public_token')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                \DB::table('vehicles')->where('id', $row->id)->update([
                    'public_token' => (string) Str::uuid(),
                ]);
            }
        });

        // Agregar campo dni_cuit a customers (el campo 'document' ya existe pero renombramos semánticamente con un alias)
        // El documento ya existe como 'document', solo agregamos el tipo específico para Argentina
        if (! Schema::hasColumn('customers', 'tax_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('tax_id')->nullable()->after('document')->comment('DNI / CUIT / CUIL');
                $table->string('tax_id_type')->nullable()->after('tax_id')->comment('dni, cuit, cuil');
            });
        }
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('public_token');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['tax_id', 'tax_id_type']);
        });
    }
};
