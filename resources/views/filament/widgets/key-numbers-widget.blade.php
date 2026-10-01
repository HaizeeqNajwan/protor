@php
    $toBoard = "document.getElementById('project-board')?.scrollIntoView({ behavior: 'smooth', block: 'start' })";
@endphp

<x-filament-widgets::widget>
    <div class="pt-kpis" wire:poll.120s.visible>
        {{-- Portfolio --}}
        <button type="button" class="pt-kpi pt-kpi--hero"
            x-on:click="$dispatch('board-filter', { scope: 'live', stage: null }); {{ $toBoard }}">
            <x-pt.progress-ring :value="$portfolio" size="sm" />
            <span class="pt-kpi-text">
                <span class="pt-kpi-label">{{ $isManager ? 'Live projects' : 'My projects' }}</span>
                <span class="pt-kpi-value pt-num">{{ $liveCount }}</span>
                <span class="pt-kpi-hint">
                    {{ $portfolio }}% overall{{ $onHold ? " · {$onHold} on hold" : '' }}
                </span>
            </span>
        </button>

        {{-- Stages --}}
        @foreach ($stageTiles as $tile)
            <button type="button" class="pt-kpi pt-kpi--stage pt-stage--{{ $tile['key'] }}"
                x-on:click="$dispatch('board-filter', { scope: 'live', stage: '{{ $tile['key'] }}' }); {{ $toBoard }}"
                title="Highlight {{ $tile['label'] }} on the board">
                <span class="pt-kpi-text">
                    <span class="pt-kpi-label">
                        <span class="pt-stage-code">{{ $tile['code'] }}</span>
                        {{ $tile['label'] }}
                    </span>
                    <span class="pt-kpi-value pt-num">{{ $tile['value'] }}</span>
                    <span class="pt-kpi-hint">{{ $tile['hint'] }}</span>
                </span>
                <span class="pt-bar pt-bar--xs pt-kpi-bar"><span style="width: {{ $tile['progress'] }}%"></span></span>
            </button>
        @endforeach

        {{-- Attention --}}
        <button type="button" @class(['pt-kpi', 'pt-kpi--alert' => $attention > 0])
            x-on:click="$dispatch('board-filter', { scope: 'attention', stage: null }); {{ $toBoard }}">
            <span class="pt-kpi-text">
                <span class="pt-kpi-label">Needs attention</span>
                <span class="pt-kpi-value pt-num">{{ $attention }}</span>
                <span class="pt-kpi-hint">see list below</span>
            </span>
        </button>

        {{-- Completion --}}
        <button type="button" @class(['pt-kpi', 'pt-kpi--good' => $ready > 0])
            x-on:click="$dispatch('board-filter', { scope: 'completed', stage: null }); {{ $toBoard }}">
            <span class="pt-kpi-text">
                @if ($isManager)
                    <span class="pt-kpi-label">Completed {{ now()->year }}</span>
                    <span class="pt-kpi-value pt-num">{{ $completedThisYear }}</span>
                    <span class="pt-kpi-hint">{{ $ready }} ready to complete</span>
                @else
                    <span class="pt-kpi-label">Works done</span>
                    <span class="pt-kpi-value pt-num">{{ $worksDone }}<small>/{{ $worksTotal }}</small></span>
                    <span class="pt-kpi-hint">{{ $ready }} ready to complete</span>
                @endif
            </span>
        </button>
    </div>
</x-filament-widgets::widget>
