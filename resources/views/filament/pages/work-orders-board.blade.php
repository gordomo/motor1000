<x-filament-panels::page>
    <div x-data="{ dragging: null }" class="m1-board">
        @foreach ($this->columns as $column)
            <section
                class="m1-board__col"
                @dragover.prevent
                @drop.prevent="if (dragging) { $wire.moveOrder(dragging, '{{ $column['value'] }}'); dragging = null; }"
            >
                <header class="m1-board__col-header">
                    <div>
                        <h3 class="text-sm font-semibold text-zinc-100">{{ $column['label'] }}</h3>
                        <p class="text-xs text-zinc-400">{{ count($column['items']) }} órdenes</p>
                    </div>
                    <x-ui.status-badge :label="count($column['items'])" tone="slate" />
                </header>

                <div class="m1-board__cards">
                    @forelse ($column['items'] as $item)
                        <article
                            class="m1-wo-card"
                            draggable="true"
                            wire:key="wo-card-{{ $item['id'] }}"
                            @dragstart="dragging = {{ $item['id'] }}"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <p class="m1-wo-card__title">{{ $item['number'] }}</p>
                                <x-ui.status-badge
                                    :label="match($item['priority']) {
                                        'urgent' => 'Urgente',
                                        'high' => 'Alta',
                                        'normal' => 'Normal',
                                        default => 'Baja',
                                    }"
                                    :tone="match($item['priority']) {
                                        'urgent' => 'red',
                                        'high' => 'amber',
                                        'normal' => 'blue',
                                        default => 'slate',
                                    }"
                                />
                            </div>

                            <p class="m1-wo-card__meta">{{ $item['customer'] }}</p>
                            <p class="m1-wo-card__meta">{{ $item['vehicle'] ?: 'Vehículo no registrado' }} · {{ $item['plate'] }}</p>
                            <p class="m1-wo-card__meta">Mecánico: {{ $item['mechanic'] }}</p>

                            <div class="m1-wo-card__footer">
                                <span class="text-xs text-zinc-400">Entrega: {{ $item['estimated_at'] }}</span>
                                <div class="flex items-center gap-2">
                                    <a class="text-xs font-semibold text-amber-300 hover:text-amber-200" href="{{ $item['viewUrl'] }}">Ver</a>
                                    <a class="text-xs font-semibold text-blue-300 hover:text-blue-200" href="{{ $item['editUrl'] }}">Editar</a>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-xl border border-dashed border-zinc-700/80 bg-zinc-900/40 p-4 text-center text-xs text-zinc-500">
                            Arrastra aquí para mover una orden
                        </div>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
