<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ParaHoyNotification;
use App\Support\ParaHoy;
use Illuminate\Console\Command;

/**
 * Todas las mañanas deja en la campanita de admin y recepción el resumen de
 * "Para hoy" (cumpleaños, recordatorios vencidos, turnos de mañana).
 *
 * Reemplaza a reminders:process, que "mandaba" los recordatorios por WhatsApp
 * al cliente: como WhatsApp no está conectado, no salía nada y los marcaba
 * como enviados igual.
 */
class AvisarParaHoyCommand extends Command
{
    protected $signature = 'para-hoy:avisar {--tenant= : Solo este taller}';

    protected $description = 'Avisa en la campanita qué hay para atender hoy (cumpleaños, recordatorios, turnos de mañana)';

    public function handle(): int
    {
        $tenants = Tenant::query()
            ->where('is_active', true)
            ->when($this->option('tenant'), fn ($q) => $q->whereKey($this->option('tenant')))
            ->get();

        $zonaOriginal = date_default_timezone_get();
        $total = 0;

        foreach ($tenants as $tenant) {
            app()->instance('current.tenant', $tenant);
            // "Hoy" y "mañana" son los del taller, como en el panel (SetTenantFromUser).
            date_default_timezone_set($tenant->timezone ?: $zonaOriginal);

            $pendientes = ParaHoy::pendientes();

            if (array_sum($pendientes) === 0) {
                continue;
            }

            $usuarios = User::query()
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->get()
                ->filter(fn (User $u): bool => $u->hasAnyRole(['admin', 'receptionist']));

            foreach ($usuarios as $usuario) {
                $usuario->notify(new ParaHoyNotification($pendientes));
                $total++;
            }

            $this->info(sprintf(
                '%s: %d cumpleaños, %d recordatorios, %d turnos. Avisados %d usuarios.',
                $tenant->name, $pendientes['cumpleanos'], $pendientes['recordatorios'], $pendientes['turnos'], $usuarios->count(),
            ));
        }

        app()->forgetInstance('current.tenant');
        date_default_timezone_set($zonaOriginal);

        $this->info("Listo. Avisos: {$total}.");

        return self::SUCCESS;
    }
}
