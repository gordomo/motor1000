<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Reminder;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Para hoy": lo que el usuario del sistema tiene que atender en el día.
 * No le escribe al cliente: le recuerda al taller, que decide qué hacer
 * (hoy, mandar el WhatsApp a mano con el mensaje ya armado).
 *
 * Todas las consultas pasan por el filtro del taller actual (TenantScope).
 */
class ParaHoy
{
    /** Clientes que cumplen años hoy. */
    public static function cumpleanos(): Builder
    {
        return Customer::query()
            ->whereNotNull('birthday')
            ->whereMonth('birthday', now()->month)
            ->whereDay('birthday', now()->day);
    }

    /** De los de hoy, los que todavía nadie atendió este año. */
    public static function cumpleanosPendientes(): Builder
    {
        return self::cumpleanos()->where(fn (Builder $q) => $q
            ->whereNull('birthday_contacted_at')
            ->orWhere('birthday_contacted_at', '<', now()->startOfYear()));
    }

    /** Recordatorios con fecha que vencen hoy o ya vencieron, sin atender. */
    public static function recordatorios(): Builder
    {
        return Reminder::query()
            ->where('status', 'pending')
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now()->endOfDay());
    }

    /** Turnos de mañana (sin los cancelados ni ausentes). */
    public static function turnosManana(): Builder
    {
        return Appointment::query()
            ->whereBetween('scheduled_at', [now()->addDay()->startOfDay(), now()->addDay()->endOfDay()])
            ->whereNotIn('status', ['cancelled', 'no_show']);
    }

    /** Los de mañana que el cliente todavía no confirmó. */
    public static function turnosSinConfirmar(): Builder
    {
        return self::turnosManana()
            ->where('status', 'scheduled')
            ->whereNull('client_confirmed_at');
    }

    /** @return array{cumpleanos: int, recordatorios: int, turnos: int} */
    public static function pendientes(): array
    {
        return [
            'cumpleanos'    => self::cumpleanosPendientes()->count(),
            'recordatorios' => self::recordatorios()->count(),
            'turnos'        => self::turnosSinConfirmar()->count(),
        ];
    }

    public static function totalPendientes(): int
    {
        return array_sum(self::pendientes());
    }

    // ---------------------------------------------------------------
    // Mensajes de WhatsApp (los manda el usuario, con un clic). Los textos
    // se editan en Plantillas de comunicación (ver Mensajes).
    // ---------------------------------------------------------------

    public static function mensajeCumpleanos(Customer $c): string
    {
        return Mensajes::cuerpo('cumpleanos', 'whatsapp', [
            'nombre' => self::nombre($c),
            'taller' => self::taller(),
        ]);
    }

    public static function mensajeRecordatorio(Reminder $r): string
    {
        return Mensajes::cuerpo('recordatorio', 'whatsapp', [
            'nombre'       => self::nombre($r->customer),
            'taller'       => self::taller(),
            'recordatorio' => rtrim((string) $r->title, '.'),
            'patente'      => $r->vehicle?->license_plate,
        ]);
    }

    public static function mensajeTurno(Appointment $a): string
    {
        return Mensajes::cuerpo('turno_manana', 'whatsapp', [
            'nombre'   => self::nombre($a->customer),
            'taller'   => self::taller(),
            'hora'     => $a->scheduled_at->format('H:i'),
            'servicio' => $a->title,
        ]);
    }

    private static function nombre(?Customer $c): string
    {
        return strtok(trim((string) $c?->name), ' ') ?: '';
    }

    private static function taller(): string
    {
        return CurrentTenant::get()?->name ?? 'el taller';
    }
}
