<?php

namespace App\Services\Booking;

use App\Models\Appointment;
use App\Models\Tenant;
use App\Scopes\TenantScope;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Calcula la disponibilidad de turnos de un taller para una fecha, según su
 * config de reservas (horarios por día, duración de franja, capacidad,
 * anticipación y días a futuro) y los turnos ya tomados.
 *
 * Capacidad = cuántos autos se atienden a la vez (slot_capacity). Un turno
 * ocupa lugar todo lo que dura (scheduled_at → ends_at o duration_minutes), no
 * solo en su hora de inicio: uno de 9:00 a 11:00 también cuenta a las 10:00.
 * La usan el turnero de la web y la agenda del panel.
 */
class SlotAvailability
{
    // Carbon::dayOfWeek → 0=domingo .. 6=sábado
    private const DAY_KEYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    /**
     * @return array<int, array{time: string, available: bool}>
     */
    public function forDate(Tenant $tenant, Carbon $date): array
    {
        $cfg = $tenant->bookingConfig();
        $tz = $tenant->timezone ?: config('app.timezone');

        $day = Carbon::parse($date->format('Y-m-d'), $tz)->startOfDay();
        $hours = $cfg['hours'][self::DAY_KEYS[$day->dayOfWeek]] ?? null;

        if (! $hours || empty($hours['open']) || ! $hours['from'] || ! $hours['to']) {
            return [];
        }

        $now = Carbon::now($tz);
        $maxDay = $now->copy()->startOfDay()->addDays((int) $cfg['max_advance_days']);
        if ($day->gt($maxDay)) {
            return [];
        }

        $minStart = $now->copy()->addHours((int) $cfg['min_advance_hours']);
        $step = max(5, (int) $cfg['slot_minutes']);
        $capacity = self::capacidad($tenant);
        $turnos = $this->turnosDelDia($tenant, $day);

        $cursor = $day->copy()->setTimeFromTimeString($hours['from']);
        $end = $day->copy()->setTimeFromTimeString($hours['to']);

        $slots = [];
        while ($cursor->lt($end)) {
            $available = $cursor->gte($minStart)
                && $this->ocupacion($turnos, $cursor, $step, $step) < $capacity;

            $slots[] = ['time' => $cursor->format('H:i'), 'available' => $available];
            $cursor = $cursor->addMinutes($step);
        }

        return $slots;
    }

    /** ¿Se puede reservar exactamente este horario? (validación del alta) */
    public function canBook(Tenant $tenant, Carbon $scheduledAt): bool
    {
        $time = $scheduledAt->format('H:i');

        foreach ($this->forDate($tenant, $scheduledAt) as $slot) {
            if ($slot['time'] === $time) {
                return $slot['available'];
            }
        }

        return false; // horario fuera de la grilla del taller
    }

    public static function capacidad(Tenant $tenant): int
    {
        return max(1, (int) ($tenant->bookingConfig()['slot_capacity'] ?? 1));
    }

    /**
     * Cuántos autos hay a la vez, como máximo, entre $desde y $desde + $minutos
     * (mirando franja por franja). Para la agenda del panel: un turno nuevo de
     * 2 horas necesita lugar en todas sus franjas.
     */
    public function ocupacionEntre(Tenant $tenant, Carbon $desde, int $minutos, ?int $exceptoId = null): int
    {
        $step = max(5, (int) $tenant->bookingConfig()['slot_minutes']);

        return $this->ocupacion($this->turnosDelDia($tenant, $desde, $exceptoId), $desde, max(1, $minutos), $step);
    }

    /**
     * Turnos vigentes del día, como intervalos [inicio, fin).
     *
     * @return Collection<int, array{0: Carbon, 1: Carbon}>
     */
    public function turnosDelDia(Tenant $tenant, Carbon $dia, ?int $exceptoId = null): Collection
    {
        $inicio = $dia->copy()->startOfDay();

        return Appointment::withoutGlobalScopes([TenantScope::class])
            ->where('tenant_id', $tenant->id)
            // Los que empiezan ese día o vienen del anterior y siguen.
            ->where('scheduled_at', '<', $inicio->copy()->addDay())
            ->where('scheduled_at', '>=', $inicio->copy()->subDay())
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->when($exceptoId, fn ($q) => $q->whereKeyNot($exceptoId))
            ->get(['id', 'scheduled_at', 'ends_at', 'duration_minutes'])
            ->map(fn (Appointment $a): array => [
                $a->scheduled_at,
                $a->ends_at ?? $a->scheduled_at->copy()->addMinutes((int) ($a->duration_minutes ?: 30)),
            ]);
    }

    /** Máximo de turnos superpuestos en alguna franja de [$desde, $desde + $minutos). */
    public function ocupacion(Collection $turnos, Carbon $desde, int $minutos, int $step): int
    {
        $hasta = $desde->copy()->addMinutes($minutos);
        $maximo = 0;

        for ($t = $desde->copy(); $t->lt($hasta); $t->addMinutes($step)) {
            $finFranja = $t->copy()->addMinutes($step)->min($hasta);
            $enFranja = $turnos->filter(fn (array $i): bool => $i[0]->lt($finFranja) && $i[1]->gt($t))->count();
            $maximo = max($maximo, $enFranja);
        }

        return $maximo;
    }
}
