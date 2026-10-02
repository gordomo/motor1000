<x-filament-panels::page>
    @php($pestanas = $this->pestanas())

    <x-filament::tabs>
        @foreach ($pestanas as $clave => $p)
            <x-filament::tabs.item
                :active="$pestana === $clave"
                :icon="$p['icon']"
                :badge="$p['pendientes'] ?: null"
                badge-color="warning"
                wire:click="$set('pestana', '{{ $clave }}')"
            >
                {{ $p['label'] }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    @livewire($pestanas[$pestana]['widget'], key('para-hoy-' . $pestana))

    <p class="text-sm text-gray-500">
        {{ __('El botón de WhatsApp abre el chat con el mensaje ya escrito: revisalo y mandalo vos. Después marcalo como atendido para que salga de la lista.') }}
    </p>
</x-filament-panels::page>
