<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Config de reservas del taller (capacidad, duración de franja,
            // horarios por día, anticipación, días a futuro). Estructura en
            // Tenant::bookingConfig(). Nullable → se aplican defaults.
            $table->json('booking_settings')->nullable()->after('accepts_online_booking');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('booking_settings');
        });
    }
};
