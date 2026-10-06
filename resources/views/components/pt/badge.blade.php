{{-- Badge — <x-pt.badge tone="danger">3 Behind</x-pt.badge> (tones: success warning danger critical info muted primary) --}}
@props(['tone' => 'muted'])

<span {{ $attributes->class(['pt-status', "pt-tone--{$tone}"]) }}>{{ $slot }}</span>
