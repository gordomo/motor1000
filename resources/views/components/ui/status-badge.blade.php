@props([
    'label',
    'tone' => 'slate',
])

@php
    $toneClass = match ($tone) {
        'amber' => 'm1-badge--amber',
        'blue' => 'm1-badge--blue',
        'green' => 'm1-badge--green',
        'red' => 'm1-badge--red',
        default => 'm1-badge--slate',
    };
@endphp

<span {{ $attributes->class(['m1-badge', $toneClass]) }}>{{ $label }}</span>
