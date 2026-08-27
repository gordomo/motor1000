<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Unifica los estados 'draft' y 'sent' de los presupuestos en 'pending'
 * (Pendiente de aprobación), según el flujo que definió el cliente: un
 * presupuesto está pendiente, aprobado o rechazado.
 *
 * En prod al 18/08/2026: 58 en draft y 4 en sent → 62 quedan en pending. Los 8
 * aprobados y los rechazados no se tocan.
 *
 * No se pierde información: quotes.sent_at ya registra si el presupuesto se le
 * envió al cliente, que es lo único que distinguía 'sent' de 'draft'.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('quotes')->whereIn('status', ['draft', 'sent'])->update(['status' => 'pending']);
    }

    public function down(): void
    {
        // Los que tienen fecha de envío eran 'sent'; el resto, 'draft'.
        // La vuelta atrás es fiel gracias a sent_at.
        DB::table('quotes')->where('status', 'pending')->whereNotNull('sent_at')->update(['status' => 'sent']);
        DB::table('quotes')->where('status', 'pending')->whereNull('sent_at')->update(['status' => 'draft']);
    }
};
