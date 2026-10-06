{{--
    Panel — titled surface used by every dashboard widget.

    <x-pt.panel eyebrow="Team" title="Staff load" class="pt-fill">
        <x-slot:actions> …badges / buttons… </x-slot:actions>
        …content…
    </x-pt.panel>
--}}
@props(['eyebrow' => null, 'title' => null])

<section {{ $attributes->class(['pt-panel']) }}>
    @if ($title || isset($actions))
        <header class="pt-panel-head">
            <div>
                @if ($eyebrow)<p class="pt-eyebrow">{{ $eyebrow }}</p>@endif
                @if ($title)<h2 class="pt-panel-title">{{ $title }}</h2>@endif
            </div>
            @isset($actions)<div class="pt-panel-actions">{{ $actions }}</div>@endisset
        </header>
    @endif

    {{ $slot }}
</section>
