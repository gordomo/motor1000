<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Marca que agrupa sucursales (ej. "341boxes"). Tenant = sucursal.
            $table->string('brand_slug')->nullable()->index()->after('slug');
            // Si la sucursal acepta reservas online (aparece en la landing).
            $table->boolean('accepts_online_booking')->default(false)->after('is_active');
            // Clave pública de la marca para la API del turnero (la setea una
            // sucursal "primaria"; las demás de la marca se agrupan por brand_slug).
            $table->string('public_api_key')->nullable()->unique()->after('brand_slug');
        });

        Schema::table('appointments', function (Blueprint $table) {
            // De dónde vino la cita (ej. "web_turnero", "panel").
            $table->string('source')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['brand_slug', 'accepts_online_booking', 'public_api_key']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
