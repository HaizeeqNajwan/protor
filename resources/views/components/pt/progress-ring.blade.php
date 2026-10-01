@props([
    'value' => 0,
    'size' => 'md',
    'tone' => 'primary',
    'label' => null,
])

@php
    $value = max(0, min(100, (float) $value));
@endphp

<div class="pt-ring pt-ring--{{ $size }} pt-tone--{{ $tone }}" role="img" aria-label="{{ $label ?? 'Progress' }} {{ round($value) }}%">
    <svg viewBox="0 0 36 36" aria-hidden="true">
        <circle class="pt-ring-ticks" cx="18" cy="18" r="17" pathLength="100" />
        <circle class="pt-ring-track" cx="18" cy="18" r="14" pathLength="100" />
        <circle class="pt-ring-value" cx="18" cy="18" r="14" pathLength="100" stroke-dasharray="{{ $value }} 100" />
    </svg>
    <div class="pt-ring-label">
        <span class="pt-ring-num">{{ round($value) }}<small>%</small></span>
        @if ($label)
            <span class="pt-ring-caption">{{ $label }}</span>
        @endif
    </div>
</div>
