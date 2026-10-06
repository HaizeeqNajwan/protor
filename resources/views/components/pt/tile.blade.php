{{--
    Bento tile — the one building block of the dashboard grid.

    <x-pt.tile tone="s2" size="md" as="button" label="Design" hint="avg 42%">4</x-pt.tile>

    tone: neutral | brand | s1 | s2 | s3 | alert | good   → colours the accent/value
    size: sm | md | lg                                     → bento span (CSS decides columns)
    as:   div | button | a
--}}
@props([
    'tone' => 'neutral',
    'size' => 'md',
    'as' => 'div',
    'label' => null,
    'hint' => null,
    'code' => null,
    'click' => null,   // Alpine expression for x-on:click
])

<{{ $as }} {{ $attributes->class(['pt-tile', "pt-tile--{$tone}", "pt-tile--{$size}"])->merge($as === 'button' ? ['type' => 'button'] : []) }} @if ($click) x-on:click="{{ $click }}" @endif>
    @isset($visual)
        <span class="pt-tile-visual">{{ $visual }}</span>
    @endisset
    <span class="pt-tile-body">
        @if ($label)
            <span class="pt-tile-label">
                @if ($code)<span class="pt-chip pt-chip--stage">{{ $code }}</span>@endif
                {{ $label }}
            </span>
        @endif
        <span class="pt-tile-value pt-num">{{ $slot }}</span>
        @if ($hint)
            <span class="pt-tile-hint">{{ $hint }}</span>
        @endif
    </span>
    @isset($footer)
        <span class="pt-tile-foot">{{ $footer }}</span>
    @endisset
</{{ $as }}>
