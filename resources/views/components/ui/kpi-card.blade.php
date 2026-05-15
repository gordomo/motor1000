@props([
    'title',
    'value',
    'hint' => null,
    'icon' => 'activity',
    'trend' => null,
    'trendLabel' => null,
    'tone' => 'slate',
    'countValue' => null,
])

@php
    $toneClass = match ($tone) {
        'amber' => 'm1-kpi--amber',
        'blue' => 'm1-kpi--blue',
        'green' => 'm1-kpi--green',
        'red' => 'm1-kpi--red',
        default => 'm1-kpi--slate',
    };
@endphp

<article {{ $attributes->class(['m1-kpi', $toneClass]) }}>
    <header class="m1-kpi__header">
        <p class="m1-kpi__title">{{ $title }}</p>
        <span class="m1-kpi__icon"><i data-lucide="{{ $icon }}"></i></span>
    </header>

    <div class="m1-kpi__value-row">
        <p class="m1-kpi__value">
            @if (! is_null($countValue))
                {{ number_format((int) $countValue, 0, ',', '.') }}
            @else
                {{ $value }}
            @endif
        </p>
        @if (! is_null($trend))
            <span class="m1-kpi__trend {{ $trend >= 0 ? 'is-positive' : 'is-negative' }}">
                {{ $trend >= 0 ? '+' : '' }}{{ number_format($trend, 1, ',', '.') }}%
            </span>
        @endif
    </div>

    @if ($hint)
        <p class="m1-kpi__hint">{{ $hint }}</p>
    @endif

    @if ($trendLabel)
        <p class="m1-kpi__caption">{{ $trendLabel }}</p>
    @endif
</article>
