<?php

namespace App\Services\Booking;

use App\Models\Appointment;
use App\Models\Tenant;
use Carbon\Carbon;

/**
 * Calcula la disponibilidad de turnos de un taller para una fecha, según su
 * config de reservas (horarios por día, duración de franja, capacidad,
 * anticipación y días a futuro) y los turnos ya tomados.
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
        $capacity = max(1, (int) $cfg['slot_capacity']);

        $cursor = $day->copy()->setTimeFromTimeString($hours['from']);
        $end = $day->copy()->setTimeFromTimeString($hours['to']);

        // Turnos ya tomados ese día, agrupados por franja (para la capacidad).
        $counts = Appointment::query()
            ->where('tenant_id', $tenant->id)
            ->whereBetween('scheduled_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->get(['scheduled_at'])
            ->groupBy(fn (Appointment $a) => $a->scheduled_at->format('H:i'))
            ->map->count();

        $slots = [];
        while ($cursor->lt($end)) {
            $time = $cursor->format('H:i');
            $booked = (int) ($counts[$time] ?? 0);
            $available = $cursor->gte($minStart) && $booked < $capacity;

            $slots[] = ['time' => $time, 'available' => $available];
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
}
