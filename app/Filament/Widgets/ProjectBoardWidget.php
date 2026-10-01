<?php

namespace App\Filament\Widgets;

use App\Enums\ProjectStage;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectTask;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

/**
 * The centre of the dashboard: every project as a card in its stage column.
 * Clicking a card opens a side panel to update work progress, switch stage
 * and complete the project — without leaving the dashboard.
 */
class ProjectBoardWidget extends Widget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.project-board-widget';

    /** live | mine | attention | completed */
    public string $scope = 'live';

    public string $search = '';

    public ?string $focusStage = null;

    public ?string $selectedId = null;

    public ?string $panelStage = null;

    protected const HEALTH_RANK = ['overdue' => 0, 'behind' => 1, 'at_risk' => 2, 'unscheduled' => 3, 'on_track' => 4, 'completed' => 5];

    /* ---------------------------------------------------------------------
     | Board interactions
     |--------------------------------------------------------------------- */

    #[On('board-filter')]
    public function applyFilter(?string $scope = null, ?string $stage = null): void
    {
        if ($scope !== null) {
            $this->scope = $scope;
        }

        $this->focusStage = $stage === $this->focusStage ? null : $stage;
    }

    public function setScope(string $scope): void
    {
        $this->scope = $scope;
    }

    #[On('board-select')]
    public function select(string $id): void
    {
        $project = $this->findProject($id);

        if (! $project) {
            return;
        }

        $this->selectedId = $project->id;
        $this->panelStage = $project->currentStage()->value;
    }

    public function closePanel(): void
    {
        $this->selectedId = null;
        $this->panelStage = null;
    }

    public function showPanelStage(string $stage): void
    {
        $this->panelStage = ProjectStage::from($stage)->value;
    }

    /* ---------------------------------------------------------------------
     | Panel actions (authorised server-side)
     |--------------------------------------------------------------------- */

    public function switchStage(string $stage): void
    {
        $project = $this->selectedProject();

        if (! $project?->canSwitchStage(auth()->user())) {
            return;
        }

        $project->switchStage(ProjectStage::from($stage));
        $this->panelStage = $stage;

        Notification::make()
            ->title("{$project->project_code} moved to {$project->currentStage()->getLabel()}")
            ->success()
            ->send();
    }

    public function saveProgress(string $workId, int $value): void
    {
        $project = $this->selectedProject();

        /** @var ProjectTask|null $work */
        $work = $project?->tasks->firstWhere('id', $workId);

        if (! $work || ! $work->canUpdateProgress(auth()->user())) {
            return;
        }

        $value = (int) (round(max(0, min(100, $value)) / 5) * 5);

        if ($value === (int) $work->progress_percentage) {
            return;
        }

        $work->recordProgress(auth()->user(), $value);

        Notification::make()
            ->title("{$work->title} · {$value}%")
            ->success()
            ->send();
    }

    public function completeProject(): void
    {
        $project = $this->selectedProject();

        if (! $project?->canComplete(auth()->user())) {
            return;
        }

        $project->markCompleted(auth()->user());

        Notification::make()
            ->title("{$project->project_code} completed")
            ->body('Nice work — the project is now marked as completed.')
            ->success()
            ->send();
    }

    /* ---------------------------------------------------------------------
     | Data
     |--------------------------------------------------------------------- */

    protected function baseQuery(): Builder
    {
        return Project::query()->visibleTo(auth()->user())->withStageData();
    }

    protected function findProject(string $id): ?Project
    {
        return $this->baseQuery()->whereKey($id)->first();
    }

    protected function selectedProject(): ?Project
    {
        if (! $this->selectedId) {
            return null;
        }

        $project = $this->baseQuery()
            ->with(['tasks' => fn ($tasks) => $tasks->inWorkOrder()])
            ->whereKey($this->selectedId)
            ->first();

        // Works check permissions against their project — reuse the loaded one (with members).
        $project?->tasks->each->setRelation('project', $project);

        return $project;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth()->user();

        /** @var Collection<int, Project> $all */
        $all = $this->baseQuery()->get();

        $live = $all->where('status', '!=', ProjectStatus::Completed);
        $needsAttention = fn (Project $p) => in_array($p->scheduleHealth(), ['overdue', 'behind', 'at_risk'], true) || $p->isStalled();

        $projects = match ($this->scope) {
            'mine' => $live->where('project_manager_id', $user?->id),
            'attention' => $live->filter($needsAttention),
            'completed' => $all->where('status', ProjectStatus::Completed),
            default => $live,
        };

        if (filled($this->search)) {
            $term = mb_strtolower(trim($this->search));
            $projects = $projects->filter(fn (Project $p) => str_contains(mb_strtolower("{$p->project_code} {$p->title} {$p->client_name} {$p->location}"), $term));
        }

        $columns = collect(ProjectStage::cases())->map(function (ProjectStage $stage) use ($projects) {
            $items = $projects
                ->filter(fn (Project $p) => $p->currentStage() === $stage)
                ->sortBy([
                    fn (Project $a, Project $b) => static::HEALTH_RANK[$a->scheduleHealth()] <=> static::HEALTH_RANK[$b->scheduleHealth()],
                    fn (Project $a, Project $b) => strcmp($a->project_code, $b->project_code),
                ])
                ->values();

            $progress = $items->map(fn (Project $p) => $p->currentStageProgress())->filter(fn ($v) => $v !== null);

            return [
                'stage' => $stage,
                'projects' => $items,
                'avg' => $progress->isEmpty() ? null : round((float) $progress->avg()),
            ];
        });

        $selected = $this->selectedProject();

        return [
            'columns' => $columns,
            'scopes' => array_filter([
                'live' => ['All live', $live->count()],
                'mine' => $user?->isManager() ? ['Managed by me', $live->where('project_manager_id', $user->id)->count()] : null,
                'attention' => ['Needs attention', $live->filter($needsAttention)->count()],
                'completed' => ['Completed', $all->where('status', ProjectStatus::Completed)->count()],
            ]),
            'total' => $projects->count(),
            'selected' => $selected,
            'panelWorks' => $selected?->tasks
                ->filter(fn (ProjectTask $w) => $w->stage?->value === $this->panelStage)
                ->values() ?? collect(),
            'isManager' => (bool) $user?->isManager(),
        ];
    }
}
