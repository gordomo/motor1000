<?php

namespace App\Console\Commands;

use App\Models\InventoryItem;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\LowStockNotification;
use App\Support\Roles;
use Illuminate\Console\Command;

/**
 * Pedido 6: revisa el inventario de cada taller y avisa a sus administradores
 * qué repuestos quedaron por debajo del mínimo configurado.
 *
 * Sigue el patrón de ProcessRemindersCommand: fija el tenant actual en el
 * contenedor para que el TenantScope filtre bien en cada vuelta.
 */
class CheckLowStockCommand extends Command
{
    protected $signature = 'stock:check {--tenant= : Revisar solo este taller}';

    protected $description = 'Avisa a los administradores qué repuestos están por debajo del stock mínimo';

    public function handle(): int
    {
        $tenants = Tenant::query()
            ->where('is_active', true)
            ->when($this->option('tenant'), fn ($q) => $q->whereKey($this->option('tenant')))
            ->get();

        $totalAvisos = 0;

        foreach ($tenants as $tenant) {
            app()->instance('current.tenant', $tenant);

            $bajos = InventoryItem::query()
                ->where('is_active', true)
                ->whereColumn('stock_quantity', '<=', 'min_stock')
                ->where('min_stock', '>', 0)
                ->orderBy('name')
                ->get();

            if ($bajos->isEmpty()) {
                continue;
            }

            $admins = User::query()
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->get()
                ->filter(fn (User $u): bool => $u->hasRole('admin'));

            if ($admins->isEmpty()) {
                $this->warn("{$tenant->name}: {$bajos->count()} repuestos bajos, pero el taller no tiene administradores activos.");
                continue;
            }

            foreach ($admins as $admin) {
                $admin->notify(new LowStockNotification($bajos));
                $totalAvisos++;
            }

            $this->info("{$tenant->name}: {$bajos->count()} repuestos bajos, avisados {$admins->count()} administradores.");
        }

        app()->forgetInstance('current.tenant');

        $this->info("Listo. Notificaciones enviadas: {$totalAvisos}.");

        return self::SUCCESS;
    }
}
