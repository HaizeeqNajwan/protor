<x-filament-widgets::widget class="pt-fill">
    <x-pt.panel eyebrow="Needs attention" title="What to look at next" class="pt-attention" wire:poll.120s.visible>
        <x-slot:actions>
            @foreach (['critical' => 'Critical', 'danger' => 'Behind', 'warning' => 'Watch', 'success' => 'Ready'] as $tone => $label)
                @if (($counts[$tone] ?? 0) > 0)
                    <x-pt.badge :tone="$tone">{{ $counts[$tone] }} {{ $label }}</x-pt.badge>
                @endif
            @endforeach
        </x-slot:actions>

        <ul class="pt-attn-list">
            @forelse ($items as $item)
                <li class="pt-attn pt-tone--{{ $item['tone'] }}">
                    @if ($item['projectId'])
                        <button type="button" class="pt-attn-link"
                            x-on:click="$dispatch('board-select', { id: '{{ $item['projectId'] }}' })">
                    @else
                        <a class="pt-attn-link" href="{{ $item['url'] }}">
                    @endif
                            <span class="pt-attn-icon"><x-filament::icon :icon="$item['icon']" /></span>
                            <span class="pt-attn-main">
                                <span class="pt-attn-text">{{ $item['text'] }}</span>
                                <span class="pt-attn-meta">
                                    <span class="pt-code">{{ $item['tag'] }}</span>
                                    @if ($item['title'])
                                        <span class="pt-dot-sep"></span>{{ $item['title'] }}
                                    @endif
                                </span>
                            </span>
                            <x-filament::icon icon="heroicon-m-chevron-right" class="pt-attn-chevron" />
                    @if ($item['projectId'])
                        </button>
                    @else
                        </a>
                    @endif
                </li>
            @empty
                <li class="pt-empty">
                    <x-filament::icon icon="heroicon-o-check-circle" class="pt-empty-icon" />
                    <p>All clear — nothing needs attention right now.</p>
                </li>
            @endforelse
        </ul>

        @if ($more > 0)
            <p class="pt-footnote">+{{ $more }} more — use “Needs attention” on the board.</p>
        @endif
    </x-pt.panel>
</x-filament-widgets::widget>
