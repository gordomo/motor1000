<x-filament-panels::page>
    {{-- Vista del mecánico: pensada para tablet o totem. Botones grandes, sin plata.
         Los nombres de los estados salen del enum: la misma orden se llama igual
         acá, en el listado, en el kanban y en el PDF. --}}

    @foreach ($this->grupos as $grupo)
        <section class="mb-8">
            <header class="mb-3">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $grupo['titulo'] }}</h2>
                <p class="text-sm text-gray-500">{{ $grupo['subtitulo'] }}</p>
            </header>

            @if ($grupo['items']->isEmpty())
                <div class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-gray-500">
                    {{ __('No hay órdenes acá.') }}
                </div>
            @else
                {{-- Una tarjeta por fila, a todo el ancho: en una tablet la grilla de
                     3 columnas dejaba la tarjeta comprimida en un costado, y los
                     puntos del checklist necesitan lugar para leerse. --}}
                <div class="grid grid-cols-1 gap-4">
                    @foreach ($grupo['items'] as $order)
                        @php
                            $trabada = $order->isBlocked();
                            $enCurso = $order->status === \App\Enums\WorkOrderStatus::Repairing;
                            // workChecklist() traduce el formato viejo: las órdenes
                            // anteriores a este flujo guardaban item/done/note y las
                            // filas salían en blanco.
                            $puntos = collect($order->workChecklist());
                            $marcados = $puntos->filter(fn ($p) => filled($p['estado']))->count();
                            $faltan = $puntos->count() - $marcados;
                        @endphp

                        <article @class([
                            'rounded-xl border-2 bg-white p-4 shadow-sm dark:bg-gray-900',
                            'border-red-500' => $trabada,
                            'border-gray-200 dark:border-gray-700' => ! $trabada,
                        ])>
                            {{-- Patente en grande: es lo que el mecánico busca --}}
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-2xl font-black tracking-wide text-gray-900 dark:text-white">
                                        {{ $order->vehicle?->license_plate ?? __('Sin patente') }}
                                    </p>
                                    <p class="text-sm text-gray-600 dark:text-gray-300">
                                        {{ trim(($order->vehicle?->brand ?? '') . ' ' . ($order->vehicle?->model ?? '')) ?: __('Vehículo sin datos') }}
                                    </p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span @class([
                                        'rounded-full px-2 py-1 text-xs font-bold',
                                        'bg-red-100 text-red-700' => $order->priority === 'urgent',
                                        'bg-amber-100 text-amber-700' => $order->priority === 'high',
                                        'bg-blue-100 text-blue-700' => $order->priority === 'normal',
                                        'bg-gray-100 text-gray-600' => $order->priority === 'low',
                                    ])>
                                        {{ match ($order->priority) {
                                            'urgent' => __('Urgente'),
                                            'high' => __('Alta'),
                                            'low' => __('Baja'),
                                            default => __('Normal'),
                                        } }}
                                    </span>
                                    <p class="mt-1 text-xs text-gray-400">{{ $order->number }}</p>
                                </div>
                            </div>

                            <p class="mt-2 text-sm text-gray-700 dark:text-gray-200">
                                <span class="font-semibold">{{ __('Trabajo:') }}</span> {{ $order->complaint }}
                            </p>

                            @if ($enCurso)
                                <p class="mt-1 text-sm font-semibold text-primary-600">
                                    {{ __('Lo está haciendo:') }} {{ $order->mechanic?->name ?? __('sin asignar') }}
                                </p>
                            @endif

                            @if ($trabada)
                                <p class="mt-3 rounded-lg bg-red-50 p-2 text-sm font-semibold text-red-700">
                                    {{ __('Trabada:') }} {{ $order->blocked_reason }}
                                </p>
                            @endif

                            {{-- ── Qué hacer con esta tarjeta ──────────────────── --}}
                            @if (! $enCurso)
                                <p class="mt-4 rounded-lg bg-gray-50 p-2 text-sm text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                    @if ($trabada)
                                        {{ __('Cuando se resuelva lo que falta, tocá "Ya se resolvió" y después empezá el trabajo.') }}
                                    @else
                                        {{ __('Tocá "Me pongo a trabajar" para tomar este auto.') }}
                                    @endif
                                </p>
                            @elseif ($puntos->isEmpty())
                                <p class="mt-4 rounded-lg bg-gray-50 p-2 text-sm text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                    {{ __('Esta orden no tiene puntos cargados. Cuando termines, tocá "Terminé el trabajo" y contá qué hiciste.') }}
                                </p>
                            @else
                                {{-- Puntos marcables directo: en una tablet, un toque por punto --}}
                                <div class="mt-4 rounded-lg border border-gray-200 dark:border-gray-700">
                                    <div class="border-b border-gray-200 px-3 py-2 dark:border-gray-700">
                                        <p class="text-xs font-bold uppercase tracking-wide text-gray-500">
                                            {{ __('Marcá cada punto') }} · {{ $marcados }}/{{ $puntos->count() }}
                                        </p>
                                        {{-- De dónde salen los puntos: si no, no se entiende --}}
                                        @if ($origen = $order->checklistOrigen())
                                            <p class="text-xs text-gray-400">{{ $origen }}</p>
                                        @endif
                                    </div>

                                    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                                        @foreach ($puntos as $indice => $punto)
                                            @php($estado = $punto['estado'] ?? null)
                                            <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                                                <div class="min-w-0 flex-1">
                                                    <p @class([
                                                        'text-sm font-medium text-gray-800 dark:text-gray-100',
                                                        'line-through opacity-60' => $estado === \App\Models\WorkOrder::PUNTO_HECHO,
                                                    ])>
                                                        {{ $punto['nombre_item'] }}
                                                    </p>
                                                    @if ($punto['estado_presupuesto'])
                                                        <p class="text-xs text-gray-400">
                                                            {{ __('venía') }} {{ $punto['estado_presupuesto'] }}
                                                            @if ($punto['observacion_previa'])
                                                                · {{ $punto['observacion_previa'] }}
                                                            @endif
                                                        </p>
                                                    @endif
                                                    @if ($estado === \App\Models\WorkOrder::PUNTO_NO_SE_PUDO)
                                                        <p class="text-xs font-semibold text-amber-600">
                                                            {{ __('No se pudo:') }} {{ $punto['aclaracion'] }}
                                                        </p>
                                                    @endif
                                                </div>

                                                <div class="flex shrink-0 items-center gap-1">
                                                    @if ($estado)
                                                        <span @class([
                                                            'rounded px-2 py-1 text-xs font-bold',
                                                            'bg-green-100 text-green-700' => $estado === \App\Models\WorkOrder::PUNTO_HECHO,
                                                            'bg-amber-100 text-amber-700' => $estado === \App\Models\WorkOrder::PUNTO_NO_SE_PUDO,
                                                        ])>
                                                            {{ \App\Models\WorkOrder::PUNTO_ESTADOS[$estado] ?? $estado }}
                                                        </span>
                                                        <x-filament::icon-button
                                                            icon="heroicon-o-arrow-uturn-left"
                                                            color="gray"
                                                            size="lg"
                                                            :label="__('Desmarcar')"
                                                            wire:click="desmarcarPunto({{ $order->id }}, {{ $indice }})"
                                                        />
                                                    @else
                                                        <x-filament::button
                                                            size="sm"
                                                            color="success"
                                                            icon="heroicon-o-check"
                                                            wire:click="marcarHecho({{ $order->id }}, {{ $indice }})"
                                                        >
                                                            {{ __('Hecho') }}
                                                        </x-filament::button>
                                                        <x-filament::button
                                                            size="sm"
                                                            color="warning"
                                                            icon="heroicon-o-x-mark"
                                                            wire:click="mountAction('noSePudo', { order: {{ $order->id }}, indice: {{ $indice }} })"
                                                        >
                                                            {{ __('No se pudo') }}
                                                        </x-filament::button>
                                                    @endif
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>

                                <p @class([
                                    'mt-3 text-sm',
                                    'font-semibold text-amber-600' => $faltan > 0,
                                    'font-semibold text-green-600' => $faltan === 0,
                                ])>
                                    @if ($faltan > 0)
                                        {{ trans_choice('{1} Falta marcar 1 punto para poder cerrar|[2,*] Faltan marcar :count puntos para poder cerrar', $faltan, ['count' => $faltan]) }}
                                    @else
                                        {{ __('Todo marcado. Ya podés cerrar la orden.') }}
                                    @endif
                                </p>
                            @endif

                            {{-- ── Botones ─────────────────────────────────────── --}}
                            <div class="mt-5 flex flex-wrap gap-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                                @if ($enCurso)
                                    @unless ($order->mechanic_id)
                                        <x-filament::button
                                            size="lg"
                                            color="primary"
                                            icon="heroicon-o-hand-thumb-up"
                                            wire:click="mountAction('hacerseCargo', { order: {{ $order->id }} })"
                                        >
                                            {{ __('Me hago cargo') }}
                                        </x-filament::button>
                                    @endunless

                                    <x-filament::button
                                        size="lg"
                                        :color="$faltan > 0 ? 'gray' : 'success'"
                                        icon="heroicon-o-check-circle"
                                        :disabled="$faltan > 0"
                                        wire:click="mountAction('completar', { order: {{ $order->id }} })"
                                    >
                                        {{ __('Terminé el trabajo') }}
                                    </x-filament::button>
                                @else
                                    @if ($trabada)
                                        <x-filament::button
                                            size="lg"
                                            color="gray"
                                            icon="heroicon-o-check"
                                            wire:click="mountAction('destrabar', { order: {{ $order->id }} })"
                                        >
                                            {{ __('Ya se resolvió') }}
                                        </x-filament::button>
                                    @else
                                        <x-filament::button
                                            size="lg"
                                            color="success"
                                            icon="heroicon-o-play"
                                            wire:click="mountAction('empezar', { order: {{ $order->id }} })"
                                        >
                                            {{ __('Me pongo a trabajar') }}
                                        </x-filament::button>

                                        <x-filament::button
                                            size="lg"
                                            color="danger"
                                            icon="heroicon-o-hand-raised"
                                            wire:click="mountAction('trabar', { order: {{ $order->id }} })"
                                        >
                                            {{ __('No puedo empezar') }}
                                        </x-filament::button>
                                    @endif
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach

    <x-filament-actions::modals />
</x-filament-panels::page>
