<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Cuándo el cliente confirmó el turno desde el link del email (doble opt-in).
            $table->timestamp('client_confirmed_at')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('client_confirmed_at');
        });
    }
};
