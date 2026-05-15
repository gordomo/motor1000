<x-filament-widgets::widget>
    <div class="grid gap-4 xl:grid-cols-12">
        <section class="fi-section p-4 xl:col-span-4">
            <header class="mb-3 flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.08em] text-zinc-500">Órdenes</p>
                    <h3 class="text-lg font-semibold text-zinc-900">Órdenes recientes</h3>
                </div>
                <x-ui.status-badge :label="count($recentWorkOrders)" tone="slate" />
            </header>

            <div class="grid gap-2.5">
                @forelse ($recentWorkOrders as $order)
                    <article class="m1-activity">
                        <span class="m1-activity__icon"><i data-lucide="clipboard-list"></i></span>
                        <div class="m1-activity__body">
                            <p class="m1-activity__title">{{ $order->number }} · {{ $order->customer?->name ?? 'Cliente no disponible' }}</p>
                            <p class="m1-activity__subtitle">
                                {{ $order->vehicle?->brand }} {{ $order->vehicle?->model }} · {{ $order->vehicle?->license_plate ?? 'Sin placa' }}
                            </p>
                        </div>
                        <x-ui.status-badge :label="$order->status->getLabel()" tone="blue" />
                    </article>
                @empty
                    <p class="rounded-lg border border-slate-200 p-3 text-sm text-slate-500">No hay órdenes registradas.</p>
                @endforelse
            </div>
        </section>

        <section class="fi-section p-4 xl:col-span-4">
            <header class="mb-3 flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.08em] text-zinc-500">CRM</p>
                    <h3 class="text-lg font-semibold text-zinc-900">Recordatorios</h3>
                </div>
                <x-ui.status-badge :label="count($reminders)" tone="slate" />
            </header>

            <div class="grid gap-2">
                @forelse ($reminders as $reminder)
                    <x-ui.activity-item
                        icon="bell"
                        :title="$reminder->title"
                        :subtitle="$reminder->customer?->name"
                        :time="$reminder->due_at?->format('d/m H:i')"
                    />
                @empty
                    <p class="rounded-lg border border-slate-200 p-3 text-sm text-slate-500">Sin recordatorios pendientes.</p>
                @endforelse
            </div>
        </section>

        <section class="fi-section p-4 xl:col-span-4">
            <header class="mb-3 flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.08em] text-zinc-500">Actividad</p>
                    <h3 class="text-lg font-semibold text-zinc-900">Cambios recientes</h3>
                </div>
                <x-ui.status-badge :label="count($activity)" tone="slate" />
            </header>

            <div class="grid gap-2">
                @forelse ($activity as $event)
                    <x-ui.activity-item
                        icon="history"
                        :title="($event->workOrder?->number ?? 'OS') . ' → ' . ucfirst(str_replace('_', ' ', (string) ($event->to_status ?? 'sin estado')))"
                        :subtitle="$event->user?->name ?? 'Sistema'"
                        :time="$event->created_at?->format('d/m H:i')"
                    />
                @empty
                    <p class="rounded-lg border border-slate-200 p-3 text-sm text-slate-500">Sin actividad reciente.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-filament-widgets::widget>
