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
    // Mensajes de WhatsApp (los manda el usuario, con un clic)
    // ---------------------------------------------------------------

    public static function mensajeCumpleanos(Customer $c): string
    {
        return sprintf(
            '¡Hola %s! Desde %s te deseamos un muy feliz cumpleaños. ¡Que lo pases genial!',
            self::nombre($c),
            self::taller(),
        );
    }

    public static function mensajeRecordatorio(Reminder $r): string
    {
        $patente = $r->vehicle?->license_plate ? " de tu vehículo {$r->vehicle->license_plate}" : '';

        return sprintf(
            'Hola %s, te escribimos de %s. Te recordamos: %s%s. ¿Querés que te reservemos un turno?',
            self::nombre($r->customer),
            self::taller(),
            rtrim((string) $r->title, '.'),
            $patente,
        );
    }

    public static function mensajeTurno(Appointment $a): string
    {
        $servicio = $a->title ? " ({$a->title})" : '';

        return sprintf(
            'Hola %s, te escribimos de %s para recordarte tu turno de mañana a las %s%s. ¿Nos confirmás que venís?',
            self::nombre($a->customer),
            self::taller(),
            $a->scheduled_at->format('H:i'),
            $servicio,
        );
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
