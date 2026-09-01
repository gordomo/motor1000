<x-filament-panels::page>
    {{-- Pedido nuevo: cuántas órdenes se cierran por día, semana y mes --}}

    {{ $this->form }}

    @php($resumen = $this->resumen)

    <div class="grid gap-4 md:grid-cols-3">
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('Cerradas hoy') }}</div>
            <div class="text-3xl font-bold text-primary-600">{{ $resumen['hoy'] }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('Esta semana') }}</div>
            <div class="text-3xl font-bold text-primary-600">{{ $resumen['semana'] }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('Este mes') }}</div>
            <div class="text-3xl font-bold text-primary-600">{{ $resumen['mes'] }}</div>
        </x-filament::section>
    </div>

    @if (($resumen['sin_cierre'] ?? 0) > 0)
        <div class="rounded-lg border-l-4 border-warning-500 bg-warning-50 p-3 text-sm dark:bg-warning-500/10">
            <p class="font-semibold">{{ __('Nota sobre las órdenes más viejas') }}</p>
            <p class="text-gray-700 dark:text-gray-300">
                {{ trans_choice(
                    '{1} 1 de estas órdenes no registró cuándo se terminó el trabajo, porque se pasó directo a Entregado. Para esa se usa la fecha de entrega.|[2,*] :count de estas órdenes no registraron cuándo se terminó el trabajo, porque se pasaron directo a Entregado. Para esas se usa la fecha de entrega.',
                    $resumen['sin_cierre'],
                    ['count' => $resumen['sin_cierre']],
                ) }}
            </p>
        </div>
    @endif

    <x-filament::section>
        <x-slot name="heading">{{ __('Período seleccionado') }}</x-slot>
        <x-slot name="description">
            {{ __('Promedios calculados sobre :dias días.', ['dias' => $resumen['dias']]) }}
        </x-slot>

        <div class="grid gap-4 md:grid-cols-4">
            <div>
                <div class="text-sm text-gray-500">{{ __('Total cerradas') }}</div>
                <div class="text-2xl font-bold">{{ $resumen['total'] }}</div>
            </div>
            <div>
                <div class="text-sm text-gray-500">{{ __('Promedio por día') }}</div>
                <div class="text-2xl font-bold">{{ $resumen['por_dia'] }}</div>
            </div>
            <div>
                <div class="text-sm text-gray-500">{{ __('Promedio por semana') }}</div>
                <div class="text-2xl font-bold">{{ $resumen['por_semana'] }}</div>
            </div>
            <div>
                <div class="text-sm text-gray-500">{{ __('Promedio por mes') }}</div>
                <div class="text-2xl font-bold">{{ $resumen['por_mes'] }}</div>
            </div>
        </div>
    </x-filament::section>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-filament::section>
            <x-slot name="heading">{{ __('Por mes') }}</x-slot>

            @forelse ($this->porMes as $fila)
                <div class="flex items-center justify-between border-b border-gray-100 py-2 last:border-0">
                    <span class="text-sm">{{ $fila['mes'] }}</span>
                    <span class="font-bold">{{ $fila['total'] }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500">{{ __('No se cerraron órdenes en el período.') }}</p>
            @endforelse
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">{{ __('Por día') }}</x-slot>

            <div class="max-h-96 overflow-y-auto">
                @forelse ($this->porDia as $fila)
                    <div class="flex items-center justify-between border-b border-gray-100 py-2 last:border-0">
                        <span class="text-sm">{{ $fila['dia']->format('d/m/Y') }}</span>
                        <span class="font-bold">{{ $fila['total'] }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">{{ __('No se cerraron órdenes en el período.') }}</p>
                @endforelse
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
