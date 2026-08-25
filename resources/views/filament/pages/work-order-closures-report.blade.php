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
