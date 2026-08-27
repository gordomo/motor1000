<x-filament-panels::page>
    {{-- Vista del mecánico: pensada para tablet/totem. Botones grandes, sin plata. --}}

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
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($grupo['items'] as $order)
                        @php
                            $trabada = $order->isBlocked();
                            $novedades = $order->hasIssues();
                            $enCurso = $order->status === \App\Enums\WorkOrderStatus::Repairing;
                        @endphp

                        <article @class([
                            'rounded-xl border-2 bg-white p-4 shadow-sm dark:bg-gray-900',
                            'border-red-500' => $trabada,
                            'border-amber-400' => ! $trabada && $novedades,
                            'border-gray-200 dark:border-gray-700' => ! $trabada && ! $novedades,
                        ])>
                            {{-- Patente bien grande: es lo que el mecánico busca --}}
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-2xl font-black tracking-wide text-gray-900 dark:text-white">
                                        {{ $order->vehicle?->license_plate ?? __('Sin patente') }}
                                    </p>
                                    <p class="text-sm text-gray-600 dark:text-gray-300">
                                        {{ trim(($order->vehicle?->brand ?? '') . ' ' . ($order->vehicle?->model ?? '')) ?: __('Vehículo sin datos') }}
                                    </p>
                                </div>
                                <span class="shrink-0 rounded-full px-2 py-1 text-xs font-bold
                                    @class([
                                        'bg-red-100 text-red-700' => $order->priority === 'urgent',
                                        'bg-amber-100 text-amber-700' => $order->priority === 'high',
                                        'bg-blue-100 text-blue-700' => $order->priority === 'normal',
                                        'bg-gray-100 text-gray-600' => $order->priority === 'low',
                                    ])">
                                    {{ match ($order->priority) {
                                        'urgent' => __('Urgente'),
                                        'high' => __('Alta'),
                                        'low' => __('Baja'),
                                        default => __('Normal'),
                                    } }}
                                </span>
                            </div>

                            <p class="mt-2 text-xs font-semibold text-gray-400">{{ $order->number }}</p>

                            <p class="mt-2 text-sm text-gray-700 dark:text-gray-200">
                                <span class="font-semibold">{{ __('Trabajo:') }}</span> {{ $order->complaint }}
                            </p>

                            @if ($enCurso)
                                <p class="mt-2 text-sm font-semibold text-primary-600">
                                    {{ __('Lo está haciendo:') }} {{ $order->mechanic?->name ?? __('sin asignar') }}
                                </p>
                            @endif

                            @if (filled($order->checklist))
                                <p class="mt-2 text-xs text-gray-500">
                                    {{ trans_choice('{1} 1 punto a trabajar|[2,*] :count puntos a trabajar', count($order->checklist), ['count' => count($order->checklist)]) }}
                                </p>
                            @endif

                            @if ($trabada)
                                <p class="mt-3 rounded-lg bg-red-50 p-2 text-sm font-semibold text-red-700">
                                    {{ __('Trabada:') }} {{ $order->blocked_reason }}
                                </p>
                            @endif

                            {{-- Botones grandes, uno por acción posible --}}
                            <div class="mt-4 flex flex-wrap gap-2">
                                @if ($enCurso)
                                    <x-filament::button
                                        size="lg"
                                        color="success"
                                        icon="heroicon-o-check-circle"
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
