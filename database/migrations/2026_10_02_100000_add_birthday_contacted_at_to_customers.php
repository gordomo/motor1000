<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Para hoy": cuándo se atendió por última vez el cumpleaños del cliente, para
 * que no vuelva a aparecer en la lista ese año. Aditiva: columna nueva y nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('birthday_contacted_at')->nullable()->after('birthday');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('birthday_contacted_at');
        });
    }
};
