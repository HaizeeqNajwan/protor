@php
    $initials = fn (?string $name) => str($name ?? '?')->explode(' ')->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('');
@endphp

<x-filament-widgets::widget class="pt-fill">
    <x-pt.panel eyebrow="Team" title="Staff load" class="pt-team">
        <x-slot:actions>
            <x-pt.badge :tone="$onLeave > 0 ? 'warning' : 'success'">{{ $onLeave }} on leave today</x-pt.badge>
        </x-slot:actions>

        <ul class="pt-team-list">
            @forelse ($rows as $row)
                <li class="pt-team-row">
                    <span class="pt-avatar pt-avatar--lg">{{ $initials($row['name']) }}</span>
                    <span class="pt-team-main">
                        <span class="pt-team-name">
                            {{ $row['name'] }}
                            @if ($row['leave'])
                                <span class="pt-tag pt-tone--warning">
                                    {{ $row['leave']->half_day ? $row['leave']->day_part->getLabel() : 'On leave' }}
                                </span>
                            @endif
                        </span>
                        <span class="pt-bar pt-bar--xs" title="Share of projects vs busiest staff">
                            <span style="width: {{ round($row['projects'] / $maxProjects * 100) }}%"></span>
                        </span>
                    </span>
                    <span class="pt-team-stats">
                        <b class="pt-num">{{ $row['projects'] }}</b>
                        <small>{{ str('project')->plural($row['projects']) }}</small>
                    </span>
                </li>
            @empty
                <li class="pt-empty"><p>No staff engineers yet.</p></li>
            @endforelse
        </ul>
    </x-pt.panel>
</x-filament-widgets::widget>
