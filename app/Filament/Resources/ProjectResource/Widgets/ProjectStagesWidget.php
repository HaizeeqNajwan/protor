<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use App\Enums\ProjectStage;
use App\Models\Project;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

/** Stage-by-stage progress header for a single project's view page. */
class ProjectStagesWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.project-stages-widget';

    public ?Model $record = null;

    #[On('project-stage-switched')]
    public function refreshStage(): void
    {
        $this->record->refresh();
    }

    /** Managers click a stage card to move the project there (any direction). */
    public function switchStage(string $stage): void
    {
        /** @var Project $project */
        $project = $this->record;

        if (! $project->canSwitchStage(auth()->user())) {
            return;
        }

        $project->switchStage(ProjectStage::from($stage));

        Notification::make()
            ->title("{$project->project_code} is now in {$project->currentStage()->getLabel()}")
            ->success()
            ->send();
    }

    public function completeProject(): void
    {
        /** @var Project $project */
        $project = $this->record;
        $project->load('tasks');

        if (! $project->canComplete(auth()->user())) {
            return;
        }

        $project->markCompleted(auth()->user());

        Notification::make()->title("{$project->project_code} completed")->success()->send();

        $this->redirect(request()->header('Referer') ?? url()->current());
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var Project $project */
        $project = $this->record->load([
            'members:id,name',
            'tasks' => fn ($tasks) => $tasks->select(['id', 'project_id', 'stage', 'status', 'progress_percentage', 'due_date', 'updated_at']),
        ]);

        return [
            'project' => $project,
            'breakdown' => $project->stageBreakdown(),
            'current' => $project->currentStage(),
            'overall' => $project->overallProgress(),
            'elapsed' => $project->timeElapsedPercentage(),
            'variance' => $project->scheduleVariance(),
            'health' => $project->scheduleHealth(),
            'canSwitch' => $project->canSwitchStage(auth()->user()),
            'canComplete' => $project->canComplete(auth()->user()),
            'remaining' => $project->remainingWorksCount(),
        ];
    }
}
