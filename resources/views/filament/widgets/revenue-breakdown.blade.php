<x-filament-widgets::widget>
    @php($m = $this->metrics)
    @php($rubros = $m['rubros'])

    <div class="grid gap-4 lg:grid-cols-2">
        <x-filament::section>
            <x-slot name="heading">{{ __('Entregado en el período, por rubro') }}</x-slot>
            <x-slot name="description">
                {{ trans_choice('{1} 1 orden entregada|[2,*] :count órdenes entregadas', $rubros['cantidad'], ['count' => $rubros['cantidad']]) }}
                @if ($m['gratis']['cantidad'] > 0)
                    · {{ trans_choice('{1} 1 sin cargo|[2,*] :count sin cargo', $m['gratis']['cantidad'], ['count' => $m['gratis']['cantidad']]) }}
                @endif
            </x-slot>

            @if ($rubros['cantidad'] === 0)
                <p class="text-sm text-gray-500">{{ __('No se entregaron órdenes en el período.') }}</p>
            @else
                @php($base = max(1, $rubros['mano_de_obra'] + $rubros['repuestos'] + $rubros['otros']))

                @foreach ([
                    ['label' => __('Mano de obra'), 'valor' => $rubros['mano_de_obra'], 'clase' => 'bg-primary-500'],
                    ['label' => __('Repuestos'),    'valor' => $rubros['repuestos'],    'clase' => 'bg-amber-500'],
                    ['label' => __('Otros'),        'valor' => $rubros['otros'],        'clase' => 'bg-gray-400'],
                ] as $fila)
                    <div class="mb-3">
                        <div class="mb-1 flex items-baseline justify-between text-sm">
                            <span class="text-gray-700 dark:text-gray-200">{{ $fila['label'] }}</span>
                            <span class="font-semibold">
                                $ {{ number_format($fila['valor'], 0, ',', '.') }}
                                <span class="text-xs text-gray-500">({{ round($fila['valor'] / $base * 100) }}%)</span>
                            </span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                            <div class="h-2 {{ $fila['clase'] }}" style="width: {{ round($fila['valor'] / $base * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach

                @if ($rubros['descuentos'] > 0)
                    <p class="mt-3 text-sm text-gray-500">
                        {{ __('Descuentos otorgados:') }} <span class="font-semibold">$ {{ number_format($rubros['descuentos'], 0, ',', '.') }}</span>
                    </p>
                @endif

                <p class="mt-2 border-t border-gray-100 pt-2 text-sm">
                    {{ __('Total entregado:') }}
                    <span class="font-bold">$ {{ number_format($rubros['total'], 0, ',', '.') }}</span>
                </p>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">{{ __('Cómo entró la plata') }}</x-slot>
            <x-slot name="description">{{ __('Cobros registrados en el período') }}</x-slot>

            @if (empty($m['cobrado']['por_medio']))
                <p class="text-sm text-gray-500">{{ __('No se registraron cobros en el período.') }}</p>
            @else
                @foreach ($m['cobrado']['por_medio'] as $medio => $monto)
                    <div class="flex items-center justify-between border-b border-gray-100 py-2 text-sm last:border-0">
                        <span>{{ __(\App\Models\Payment::METHODS[$medio] ?? $medio) }}</span>
                        <span class="font-semibold">$ {{ number_format($monto, 0, ',', '.') }}</span>
                    </div>
                @endforeach

                <p class="mt-2 border-t border-gray-100 pt-2 text-sm">
                    {{ __('Total cobrado:') }}
                    <span class="font-bold">$ {{ number_format($m['cobrado']['monto'], 0, ',', '.') }}</span>
                </p>
            @endif
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
