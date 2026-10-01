@php
    /** @var \App\Models\Project $record */
    $record = $getRecord();
    $breakdown = $record->stageBreakdown();
    $current = $record->currentStage();
@endphp

<div class="pt-mini-track" title="Overall {{ round($record->overallProgress()) }}%">
    <div class="pt-mini-segs">
        @foreach (\App\Enums\ProjectStage::cases() as $stage)
            @php
                $seg = $breakdown[$stage->value];
            @endphp
            <span @class([
                'pt-mini-seg',
                'pt-mini-seg--active' => $current === $stage,
                'pt-mini-seg--done' => $seg['total'] > 0 && $seg['completed'] === $seg['total'],
                'pt-mini-seg--empty' => $seg['total'] === 0,
            ]) title="{{ $stage->getLabel() }} · {{ $seg['progress'] === null ? 'no works' : round($seg['progress']) . '%' }}">
                <i style="width: {{ $seg['progress'] ?? 0 }}%"></i>
            </span>
        @endforeach
    </div>
    <div class="pt-mini-caption">
        <span>{{ $current->getLabel() }}</span>
        <b class="pt-num">{{ round($record->overallProgress()) }}%</b>
    </div>
</div>
