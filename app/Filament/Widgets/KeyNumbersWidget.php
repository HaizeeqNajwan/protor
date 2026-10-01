<?php

namespace App\Filament\Widgets;

use App\Enums\ProjectStage;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * One row of headline numbers. Every tile is a shortcut: it filters the
 * project board below (stage tiles highlight that column).
 */
class KeyNumbersWidget extends Widget
{
    protected static ?int $sort = 0;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.key-numbers-widget';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth()->user();

        /** @var Collection<int, Project> $all */
        $all = Project::query()->visibleTo($user)->withStageData()->get();
        $live = $all->where('status', '!=', ProjectStatus::Completed);

        $attention = $live->filter(fn (Project $p) => in_array($p->scheduleHealth(), ['overdue', 'behind', 'at_risk'], true) || $p->isStalled());
        $ready = $live->filter(fn (Project $p) => $p->hasWorks() && $p->remainingWorksCount() === 0);

        $stageTiles = collect(ProjectStage::cases())->map(function (ProjectStage $stage) use ($live) {
            $inStage = $live->filter(fn (Project $p) => $p->currentStage() === $stage);
            $progress = $inStage->map(fn (Project $p) => $p->currentStageProgress())->filter(fn ($v) => $v !== null);

            return [
                'key' => $stage->value,
                'label' => $stage->getLabel(),
                'code' => $stage->code(),
                'icon' => $stage->getIcon(),
                'value' => $inStage->count(),
                'hint' => $progress->isEmpty() ? 'No works yet' : 'avg '.round((float) $progress->avg()).'% of stage',
                'progress' => $progress->isEmpty() ? 0 : round((float) $progress->avg()),
            ];
        });

        $works = $live->flatMap(fn (Project $p) => $p->tasks);

        return [
            'isManager' => (bool) $user?->isManager(),
            'liveCount' => $live->count(),
            'onHold' => $live->where('status', ProjectStatus::OnHold)->count(),
            'portfolio' => $live->isEmpty() ? 0 : round((float) $live->avg(fn (Project $p) => $p->overallProgress())),
            'stageTiles' => $stageTiles,
            'attention' => $attention->count(),
            'ready' => $ready->count(),
            'worksDone' => $works->where('progress_percentage', '>=', 100)->count(),
            'worksTotal' => $works->count(),
            'completedThisYear' => $all
                ->where('status', ProjectStatus::Completed)
                ->filter(fn (Project $p) => $p->actual_completion_date?->isCurrentYear())
                ->count(),
        ];
    }
}
