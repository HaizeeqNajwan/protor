@php
    $toBoard = "document.getElementById('project-board')?.scrollIntoView({ behavior: 'smooth', block: 'start' })";
    $go = fn (string $scope, ?string $stage = null) => "\$dispatch('board-filter', { scope: '{$scope}', stage: ".($stage ? "'{$stage}'" : 'null').' }); '.$toBoard;
@endphp

<x-filament-widgets::widget>
    {{-- Bento grid: hero (2 rows) · three stage tiles · alert · completed --}}
    <div class="pt-bento" wire:poll.120s.visible>
        <x-pt.tile as="button" tone="brand" size="lg" class="pt-tile--hero"
            :click="$go('live')"
            :label="$isManager ? 'Live projects' : 'My projects'"
            :hint="$portfolio . '% overall' . ($onHold ? ' · ' . $onHold . ' on hold' : '')">
            <x-slot:visual><x-pt.progress-ring :value="$portfolio" size="lg" /></x-slot:visual>
            {{ $liveCount }}
        </x-pt.tile>

        @foreach ($stageTiles as $tile)
            <x-pt.tile as="button" :tone="match ($tile['key']) { 'pre_design' => 's1', 'design' => 's2', default => 's3' }" size="md"
                :click="$go('live', $tile['key'])"
                :title="'Highlight ' . $tile['label'] . ' on the board'"
                :code="$tile['code']" :label="$tile['label']" :hint="$tile['hint']">
                {{ $tile['value'] }}
                <x-slot:footer><span class="pt-bar pt-bar--xs"><span style="width: {{ $tile['progress'] }}%"></span></span></x-slot:footer>
            </x-pt.tile>
        @endforeach

        <x-pt.tile as="button" :tone="$attention > 0 ? 'alert' : 'neutral'" size="sm"
            :click="$go('attention')" label="Needs attention" hint="see list below">
            {{ $attention }}
        </x-pt.tile>

        @if ($isManager)
            <x-pt.tile as="button" :tone="$ready > 0 ? 'good' : 'neutral'" size="sm"
                :click="$go('completed')" :label="'Completed ' . now()->year" :hint="$ready . ' ready to complete'">
                {{ $completedThisYear }}
            </x-pt.tile>
        @else
            <x-pt.tile as="button" :tone="$ready > 0 ? 'good' : 'neutral'" size="sm"
                :click="$go('completed')" label="Works done" :hint="$ready . ' ready to complete'">
                {{ $worksDone }}<small>/{{ $worksTotal }}</small>
            </x-pt.tile>
        @endif
    </div>
</x-filament-widgets::widget>
