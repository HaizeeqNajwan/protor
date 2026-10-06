@php
    use App\Enums\ProjectStage;

    $healthLabels = [
        'overdue' => ['Overdue', 'critical'],
        'behind' => ['Behind plan', 'danger'],
        'at_risk' => ['At risk', 'warning'],
        'on_track' => ['On track', 'success'],
        'unscheduled' => ['No schedule', 'muted'],
        'completed' => ['Completed', 'success'],
    ];
    [$healthLabel, $healthTone] = $healthLabels[$health];
    $target = $project->target_completion_date;
@endphp

<x-filament-widgets::widget>
    <section class="pt-panel pt-single">
        <span class="pt-corner pt-corner--tl"></span>
        <span class="pt-corner pt-corner--br"></span>

        <header class="pt-panel-head">
            <div>
                <p class="pt-eyebrow">{{ $project->project_code }} · Project stage</p>
                <h2 class="pt-panel-title">Currently in {{ $current->getLabel() }}</h2>
            </div>
            <div class="pt-head-meta">
                @if ($canSwitch)
                    <span class="pt-hint">Click a stage to switch</span>
                @endif
                <span class="pt-status pt-tone--{{ $healthTone }}">{{ $healthLabel }}</span>
            </div>
        </header>

        <div class="pt-single-grid">
            <div class="pt-single-kpis">
                <x-pt.progress-ring :value="$overall" size="lg" label="Overall" :tone="$healthTone === 'muted' ? 'primary' : $healthTone" />

                <dl class="pt-kv">
                    <div>
                        <dt>Schedule elapsed</dt>
                        <dd class="pt-num">{{ $elapsed === null ? '—' : round($elapsed) . '%' }}</dd>
                    </div>
                    <div>
                        <dt>Variance</dt>
                        <dd @class(['pt-num', 'pt-text-danger' => $variance !== null && $variance < -8, 'pt-text-success' => $variance !== null && $variance >= 0])>
                            {{ $variance === null ? '—' : sprintf('%+d%%', round($variance)) }}
                        </dd>
                    </div>
                    <div>
                        <dt>Target</dt>
                        <dd class="pt-num">{{ $target?->format('d M Y') ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            <ol class="pt-stages pt-stages--compact">
                @foreach (ProjectStage::cases() as $stage)
                    @php
                        $seg = $breakdown[$stage->value];
                        $isCurrent = $current === $stage;
                        $state = match (true) {
                            $isCurrent => 'active',
                            $seg['total'] === 0 => 'empty',
                            $seg['completed'] === $seg['total'] => 'done',
                            default => 'pending',
                        };
                    @endphp

                    <li @class([
                        'pt-stage',
                        'pt-stage--' . $stage->value,
                        'pt-stage-state--' . $state,
                        'pt-stage--clickable' => $canSwitch && ! $isCurrent,
                    ])
                        @if ($canSwitch && ! $isCurrent)
                            role="button" tabindex="0"
                            wire:click="switchStage('{{ $stage->value }}')"
                            wire:keydown.enter="switchStage('{{ $stage->value }}')"
                            wire:confirm="Move {{ $project->project_code }} to {{ $stage->getLabel() }}?"
                            title="Switch project to {{ $stage->getLabel() }}"
                        @endif
                    >
                        @if ($isCurrent)
                            <span class="pt-stage-flag">Current stage</span>
                        @endif
                        <div class="pt-stage-head">
                            <span class="pt-stage-code">{{ $stage->code() }}</span>
                            <x-filament::icon :icon="$stage->getIcon()" class="pt-stage-icon" />
                            <span class="pt-stage-name">{{ $stage->getLabel() }}</span>
                            <span class="pt-stage-pct pt-num">{{ $seg['progress'] === null ? '—' : round($seg['progress']) . '%' }}</span>
                        </div>
                        <p class="pt-stage-desc">{{ $stage->getDescription() }}</p>
                        <div class="pt-bar pt-bar--lg"><span style="width: {{ $seg['progress'] ?? 0 }}%"></span></div>
                        <dl class="pt-stage-stats">
                            <div>
                                <dt>Done</dt>
                                <dd class="pt-num">{{ $seg['completed'] }}<small>/{{ $seg['total'] }}</small></dd>
                            </div>
                            <div>
                                <dt>Overdue</dt>
                                <dd @class(['pt-num', 'pt-text-danger' => $seg['overdue'] > 0])>{{ $seg['overdue'] }}</dd>
                            </div>
                            <div>
                                <dt>State</dt>
                                <dd>{{ ['empty' => 'No works', 'done' => 'Complete', 'active' => 'Current', 'pending' => 'Queued'][$state] }}</dd>
                            </div>
                        </dl>
                    </li>
                @endforeach
            </ol>
        </div>

        @if ($project->status === \App\Enums\ProjectStatus::Completed)
            <div class="pt-complete-bar is-done">
                <x-filament::icon icon="heroicon-m-check-badge" class="pt-complete-icon" />
                <span>Project completed on <b>{{ $project->actual_completion_date?->format('d M Y') }}</b>.</span>
            </div>
        @elseif ($canComplete)
            <div class="pt-complete-bar">
                <x-filament::icon icon="heroicon-m-sparkles" class="pt-complete-icon" />
                <span>All works are at 100%. Ready to close out this project.</span>
                <button type="button" class="pt-btn-complete" wire:click="completeProject"
                    wire:confirm="Mark {{ $project->project_code }} as completed?">
                    <x-filament::icon icon="heroicon-m-check-badge" />
                    Complete project
                </button>
            </div>
        @elseif ($project->tasks->isNotEmpty())
            <div class="pt-complete-bar is-locked">
                <x-filament::icon icon="heroicon-m-lock-closed" class="pt-complete-icon" />
                <span><b>{{ $remaining }}</b> {{ str('work')->plural($remaining) }} still below 100% — the <i>Complete project</i> button unlocks when every work is done.</span>
            </div>
        @endif
    </section>
</x-filament-widgets::widget>
