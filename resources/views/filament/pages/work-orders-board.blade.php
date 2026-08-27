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
                        <h3 class="text-sm font-semibold text-zinc-800">{{ $column['label'] }}</h3>
                        <p class="text-xs text-zinc-500">{{ count($column['items']) }} órdenes</p>
                    </div>
                    <x-ui.status-badge :label="count($column['items'])" tone="slate" />
                </header>

                <div class="m1-board__cards">
                    @forelse ($column['items'] as $item)
                        {{-- Trabada o con algo sin hacer: el cliente pidió que se note --}}
                        <article
                            @class([
                                'm1-wo-card',
                                'ring-2 ring-red-500' => $item['blocked'],
                                'ring-2 ring-amber-400' => ! $item['blocked'] && $item['hasIssues'],
                            ])
                            draggable="true"
                            wire:key="wo-card-{{ $item['id'] }}"
                            @dragstart="dragging = {{ $item['id'] }}"
                        >
                            @if ($item['blocked'])
                                <p class="mb-1 rounded bg-red-50 px-2 py-1 text-xs font-semibold text-red-700">
                                    Trabada: {{ $item['blockedReason'] }}
                                </p>
                            @elseif ($item['hasIssues'])
                                <p class="mb-1 rounded bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700">
                                    {{ $item['issuesCount'] }} punto(s) sin hacer
                                </p>
                            @endif
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
                                <span class="text-xs text-zinc-500">Entrega: {{ $item['estimated_at'] }}</span>
                                <div class="flex items-center gap-2">
                                    <a class="text-xs font-semibold text-amber-600 hover:text-amber-700" href="{{ $item['viewUrl'] }}">Ver</a>
                                    <a class="text-xs font-semibold text-blue-600 hover:text-blue-700" href="{{ $item['editUrl'] }}">Editar</a>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-xl border border-dashed border-zinc-300 bg-white/60 p-4 text-center text-xs text-zinc-500">
                            Arrastra aquí para mover una orden
                        </div>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
