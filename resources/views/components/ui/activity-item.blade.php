@props([
    'title',
    'subtitle' => null,
    'time' => null,
    'icon' => 'circle',
])

<div {{ $attributes->class(['m1-activity']) }}>
    <span class="m1-activity__icon"><i data-lucide="{{ $icon }}"></i></span>
    <div class="m1-activity__body">
        <p class="m1-activity__title">{{ $title }}</p>
        @if ($subtitle)
            <p class="m1-activity__subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    @if ($time)
        <span class="m1-activity__time">{{ $time }}</span>
    @endif
</div>
