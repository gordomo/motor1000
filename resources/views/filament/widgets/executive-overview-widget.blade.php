<x-filament-widgets::widget>
    <section class="fi-section p-4">
        <header class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.08em] text-zinc-500">{{ __('Resumen operativo') }}</p>
                <h2 class="text-2xl font-bold text-zinc-900">{{ __('Centro de control del taller') }}</h2>
            </div>
            @if (is_null($revenueVariation))
                <x-ui.status-badge :label="__('Sin comparativa mensual')" tone="slate" />
            @else
                <x-ui.status-badge
                    :label="($revenueVariation >= 0 ? '+' : '') . number_format($revenueVariation, 1, ',', '.') . __('% vs mes anterior')"
                    :tone="$revenueVariation >= 0 ? 'green' : 'red'"
                />
            @endif
        </header>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <x-ui.kpi-card
                :title="__('Ingresos del mes')"
                :value="'$ ' . number_format($currentMonthRevenue, 2, ',', '.')"
                icon="wallet"
                tone="amber"
                :trend="$revenueVariation"
            />
            <x-ui.kpi-card :title="__('OS abiertas')" :value="(string) $kpis['open_work_orders']" icon="clipboard-list" tone="blue" />
            <x-ui.kpi-card :title="__('Completadas del mes')" :value="(string) $kpis['completed_this_month']" icon="check-circle" tone="green" />
            <x-ui.kpi-card :title="__('Recordatorios 7 días')" :value="(string) $kpis['pending_reminders']" icon="bell-ring" tone="slate" />
        </div>
    </section>
</x-filament-widgets::widget>
