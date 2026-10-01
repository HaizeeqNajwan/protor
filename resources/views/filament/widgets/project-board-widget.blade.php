@php
    use App\Enums\ProjectStage;
    use App\Enums\ProjectStatus;
    use App\Filament\Resources\ProjectResource;

    $healthMeta = [
        'overdue' => ['Overdue', 'critical'],
        'behind' => ['Behind plan', 'danger'],
        'at_risk' => ['At risk', 'warning'],
        'on_track' => ['On track', 'success'],
        'unscheduled' => ['No schedule', 'muted'],
        'completed' => ['Completed', 'success'],
    ];

    $initials = fn (?string $name) => str($name ?? '?')->explode(' ')->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('');
@endphp

<x-filament-widgets::widget id="project-board">
    <section class="pt-panel pt-board" wire:poll.120s.visible>
        <span class="pt-corner pt-corner--tl"></span>
        <span class="pt-corner pt-corner--br"></span>

        {{-- Toolbar --}}
        <header class="pt-board-toolbar">
            <div>
                <p class="pt-eyebrow">Project board</p>
                <h2 class="pt-panel-title">Where every project stands</h2>
            </div>

            <div class="pt-board-controls">
                <nav class="pt-seg-control" aria-label="Filter projects">
                    @foreach ($scopes as $key => [$label, $count])
                        <button type="button" wire:click="setScope('{{ $key }}')"
                            @class(['is-active' => $scope === $key, 'is-alert' => $key === 'attention' && $count > 0])>
                            {{ $label }} <span class="pt-chip-count">{{ $count }}</span>
                        </button>
                    @endforeach
                </nav>

                <label class="pt-search">
                    <x-filament::icon icon="heroicon-m-magnifying-glass" class="pt-search-icon" />
                    <input type="search" placeholder="Search code, title, client…" wire:model.live.debounce.300ms="search">
                </label>
            </div>
        </header>

        {{-- Stage columns --}}
        <div class="pt-board-cols" wire:loading.class="pt-is-loading" wire:target="setScope,search,applyFilter">
            @foreach ($columns as $column)
                @php
                    $stage = $column['stage'];
                @endphp
                <div wire:key="col-{{ $stage->value }}" @class([
                    'pt-col',
                    'pt-stage--' . $stage->value,
                    'is-focused' => $focusStage === $stage->value,
                    'is-dimmed' => $focusStage && $focusStage !== $stage->value,
                ])>
                    <div class="pt-col-head">
                        <span class="pt-stage-code">{{ $stage->code() }}</span>
                        <x-filament::icon :icon="$stage->getIcon()" class="pt-stage-icon" />
                        <span class="pt-col-name">{{ $stage->getLabel() }}</span>
                        <span class="pt-col-count">{{ $column['projects']->count() }}</span>
                    </div>
                    <div class="pt-col-sub">
                        <span>{{ $stage->getDescription() }}</span>
                        @if ($column['avg'] !== null)
                            <span class="pt-num">avg {{ $column['avg'] }}%</span>
                        @endif
                    </div>

                    <div class="pt-col-body">
                        @forelse ($column['projects'] as $project)
                            @php
                                [$healthLabel, $healthTone] = $healthMeta[$project->scheduleHealth()];
                                $stageProgress = $project->currentStageProgress();
                                $target = $project->target_completion_date;
                                $daysLeft = $target ? (int) today()->diffInDays($target, false) : null;
                                $team = $project->members->take(3);
                            @endphp

                            <button type="button" wire:key="card-{{ $project->id }}"
                                wire:click="select('{{ $project->id }}')"
                                @class(['pt-card', 'pt-health--' . $healthTone, 'is-selected' => $selectedId === $project->id])>
                                <span class="pt-card-top">
                                    <span class="pt-code">{{ $project->project_code }}</span>
                                    <span class="pt-dot pt-tone--{{ $healthTone }}" title="{{ $healthLabel }}"></span>
                                    @if ($project->status === ProjectStatus::OnHold)
                                        <span class="pt-tag pt-tone--warning">On hold</span>
                                    @endif
                                    <span class="pt-card-due">
                                        @if ($project->status === ProjectStatus::Completed)
                                            Done {{ $project->actual_completion_date?->format('d M') }}
                                        @elseif ($daysLeft !== null)
                                            @if ($daysLeft < 0)
                                                <b class="pt-text-danger">{{ abs($daysLeft) }}d late</b>
                                            @else
                                                T–{{ $daysLeft }}d
                                            @endif
                                        @endif
                                    </span>
                                </span>

                                <span class="pt-card-title">{{ $project->title }}</span>
                                <span class="pt-card-client">{{ $project->client_name }}</span>

                                <span class="pt-card-progress">
                                    <span class="pt-card-progress-label">
                                        <span>{{ $stage->getLabel() }} stage</span>
                                        <b class="pt-num">{{ $stageProgress === null ? '—' : round($stageProgress) . '%' }}</b>
                                    </span>
                                    <span class="pt-bar"><span style="width: {{ $stageProgress ?? 0 }}%"></span></span>
                                </span>

                                <span class="pt-card-foot">
                                    <span class="pt-avatars">
                                        @forelse ($team as $member)
                                            <span class="pt-avatar" title="{{ $member->name }}">{{ $initials($member->name) }}</span>
                                        @empty
                                            <span class="pt-muted">No staff assigned</span>
                                        @endforelse
                                        @if ($project->members->count() > 3)
                                            <span class="pt-avatar pt-avatar--more">+{{ $project->members->count() - 3 }}</span>
                                        @endif
                                    </span>
                                    <span class="pt-card-overall" title="Overall progress across all stages">
                                        Overall <b class="pt-num">{{ round($project->overallProgress()) }}%</b>
                                    </span>
                                </span>
                            </button>
                        @empty
                            <div class="pt-col-empty">No projects here</div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Slide-over project panel --}}
        @if ($selected)
            @php
                [$healthLabel, $healthTone] = $healthMeta[$selected->scheduleHealth()];
                $breakdown = $selected->stageBreakdown();
                $current = $selected->currentStage();
                $canSwitch = $selected->canSwitchStage(auth()->user());
                $canComplete = $selected->canComplete(auth()->user());
                $remaining = $selected->remainingWorksCount();
                $variance = $selected->scheduleVariance();
            @endphp

            <div class="pt-drawer-wrap" x-data x-on:keydown.escape.window="$wire.closePanel()" wire:key="drawer-{{ $selected->id }}">
                <div class="pt-drawer-backdrop" wire:click="closePanel"></div>

                <aside class="pt-drawer" role="dialog" aria-modal="true" aria-label="{{ $selected->project_code }}">
                    <header class="pt-drawer-head">
                        <div class="pt-drawer-ident">
                            <span class="pt-code">{{ $selected->project_code }}</span>
                            <span class="pt-status pt-tone--{{ $healthTone }}">{{ $healthLabel }}</span>
                        </div>
                        <button type="button" class="pt-icon-btn" wire:click="closePanel" aria-label="Close">
                            <x-filament::icon icon="heroicon-m-x-mark" />
                        </button>
                    </header>

                    <div class="pt-drawer-body">
                        <h3 class="pt-drawer-title">{{ $selected->title }}</h3>
                        <p class="pt-proj-sub">
                            {{ $selected->client_name }}@if ($selected->location)<span class="pt-dot-sep"></span>{{ $selected->location }}@endif
                        </p>

                        <div class="pt-drawer-kpis">
                            <x-pt.progress-ring :value="$selected->overallProgress()" :tone="$healthTone === 'muted' ? 'primary' : $healthTone" label="Overall" />
                            <dl class="pt-kv">
                                <div>
                                    <dt>Works done</dt>
                                    <dd class="pt-num">{{ $selected->tasks->count() - $remaining }}<small class="pt-muted">/{{ $selected->tasks->count() }}</small></dd>
                                </div>
                                <div>
                                    <dt>Target</dt>
                                    <dd class="pt-num">{{ $selected->target_completion_date?->format('d M Y') ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt>Vs plan</dt>
                                    <dd @class(['pt-num', 'pt-text-danger' => $variance !== null && $variance < -8, 'pt-text-success' => $variance !== null && $variance >= 0])>
                                        {{ $variance === null ? '—' : sprintf('%+d%%', round($variance)) }}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        {{-- Stage switcher + work tabs --}}
                        <div class="pt-drawer-section">
                            <div class="pt-drawer-label">
                                <span>Stage</span>
                                @if ($canSwitch)
                                    <span class="pt-hint">Set current stage with ●</span>
                                @endif
                            </div>

                            <div class="pt-stage-tabs" role="tablist">
                                @foreach (ProjectStage::cases() as $stage)
                                    @php
                                        $seg = $breakdown[$stage->value];
                                    @endphp
                                    <div @class([
                                        'pt-stage-tab',
                                        'pt-stage--' . $stage->value,
                                        'is-open' => $panelStage === $stage->value,
                                        'is-current' => $current === $stage,
                                    ])>
                                        <button type="button" class="pt-stage-tab-btn" role="tab" wire:click="showPanelStage('{{ $stage->value }}')">
                                            <span class="pt-stage-code">{{ $stage->code() }}</span>
                                            <span class="pt-stage-tab-name">{{ $stage->getLabel() }}</span>
                                            <span class="pt-num pt-stage-tab-pct">{{ $seg['progress'] === null ? '—' : round($seg['progress']) . '%' }}</span>
                                            <span class="pt-bar pt-bar--xs"><span style="width: {{ $seg['progress'] ?? 0 }}%"></span></span>
                                        </button>

                                        @if ($current === $stage)
                                            <span class="pt-current-pill">Current</span>
                                        @elseif ($canSwitch)
                                            <button type="button" class="pt-set-current" wire:click="switchStage('{{ $stage->value }}')"
                                                title="Move project to {{ $stage->getLabel() }}">● Set current</button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <ul class="pt-work-list">
                            @forelse ($panelWorks as $work)
                                @php
                                    $editable = $work->canUpdateProgress(auth()->user());
                                @endphp
                                <li wire:key="work-{{ $work->id }}-{{ $work->progress_percentage }}" @class(['pt-work', 'is-done' => $work->progress_percentage >= 100])
                                    x-data="{ v: {{ (int) $work->progress_percentage }} }">
                                    <div class="pt-work-head">
                                        <span class="pt-work-check">
                                            @if ($work->progress_percentage >= 100)
                                                <x-filament::icon icon="heroicon-m-check" />
                                            @endif
                                        </span>
                                        <span class="pt-work-title">{{ $work->title }}</span>
                                        <b class="pt-num pt-work-pct" x-text="v + '%'">{{ $work->progress_percentage }}%</b>
                                    </div>

                                    @if ($editable)
                                        <input type="range" min="0" max="100" step="5" class="pt-range"
                                            x-model.number="v"
                                            x-bind:style="'--v:' + v + '%'"
                                            x-on:change="$wire.saveProgress('{{ $work->id }}', v)"
                                            aria-label="Progress for {{ $work->title }}">
                                        <div class="pt-quick">
                                            @foreach ([0, 25, 50, 75, 100] as $q)
                                                <button type="button" x-on:click="v = {{ $q }}; $wire.saveProgress('{{ $work->id }}', {{ $q }})"
                                                    x-bind:class="{ 'is-on': v === {{ $q }} }">{{ $q }}%</button>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="pt-bar"><span style="width: {{ $work->progress_percentage }}%"></span></span>
                                    @endif

                                    @if ($last = $work->lastProgressUpdate())
                                        <span class="pt-work-meta">Updated by {{ $last['by_name'] }} · {{ $last['at']->diffForHumans() }}</span>
                                    @endif
                                </li>
                            @empty
                                <li class="pt-col-empty">No works in this stage.</li>
                            @endforelse
                        </ul>

                        @if ($isManager && $selected->status !== ProjectStatus::Completed)
                            <p class="pt-footnote">
                                <x-filament::icon icon="heroicon-m-information-circle" class="pt-footnote-icon" />
                                Progress is updated by the assigned staff.
                            </p>
                        @endif
                    </div>

                    <footer class="pt-drawer-foot">
                        <a href="{{ ProjectResource::getUrl('view', ['record' => $selected]) }}" class="pt-btn-ghost">Open project</a>

                        @if ($selected->status === ProjectStatus::Completed)
                            <span class="pt-status pt-tone--success">
                                <x-filament::icon icon="heroicon-m-check-badge" class="pt-status-icon" />
                                Completed {{ $selected->actual_completion_date?->format('d M Y') }}
                            </span>
                        @elseif ($canComplete)
                            <button type="button" class="pt-btn-complete" wire:click="completeProject"
                                wire:confirm="Mark {{ $selected->project_code }} as completed?">
                                <x-filament::icon icon="heroicon-m-check-badge" />
                                Complete project
                            </button>
                        @else
                            <span class="pt-btn-complete is-disabled" title="Every work must reach 100% first">
                                <x-filament::icon icon="heroicon-m-lock-closed" />
                                {{ $remaining }} {{ str('work')->plural($remaining) }} to go
                            </span>
                        @endif
                    </footer>
                </aside>
            </div>
        @endif
    </section>
</x-filament-widgets::widget>
