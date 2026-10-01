<?php

namespace App\Filament\Widgets;

use App\Enums\ProjectStatus;
use App\Filament\Resources\AuthoritySubmissionResource;
use App\Filament\Resources\LeaveRequestResource;
use App\Models\AuthoritySubmission;
use App\Models\LeaveRequest;
use App\Models\Project;
use Filament\Widgets\Widget;

/** One prioritised to-do feed instead of scattered stats. */
class AttentionWidget extends Widget
{
    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.attention-widget';

    public function getColumnSpan(): int|string|array
    {
        // Managers also see the Team panel beside this one.
        return auth()->user()?->isManager() ? ['default' => 1, 'xl' => 2] : 'full';
    }

    /** critical → info */
    protected const SEVERITY = ['critical' => 0, 'danger' => 1, 'warning' => 2, 'success' => 3, 'info' => 4];

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth()->user();
        $items = collect();

        $projects = Project::query()
            ->visibleTo($user)
            ->where('status', '!=', ProjectStatus::Completed)
            ->withStageData()
            ->get();

        foreach ($projects as $project) {
            $health = $project->scheduleHealth();

            if ($project->hasWorks() && $project->remainingWorksCount() === 0) {
                $items->push($this->projectItem($project, 'success', 'heroicon-m-check-badge', 'All works done — ready to complete'));
            }

            if ($health === 'overdue') {
                $days = (int) abs(today()->diffInDays($project->target_completion_date));
                $items->push($this->projectItem($project, 'critical', 'heroicon-m-shield-exclamation', "{$days} day(s) past target date"));
            } elseif ($health === 'behind') {
                $items->push($this->projectItem($project, 'danger', 'heroicon-m-arrow-trending-down', sprintf('Behind plan (%+d%%)', round($project->scheduleVariance()))));
            } elseif ($health === 'at_risk') {
                $items->push($this->projectItem($project, 'warning', 'heroicon-m-exclamation-triangle', sprintf('At risk (%+d%% vs plan)', round($project->scheduleVariance()))));
            }

            if ($project->isStalled()) {
                $since = $project->lastActivityAt()?->diffForHumans() ?? 'a while';
                $items->push($this->projectItem($project, 'warning', 'heroicon-m-pause-circle', "No progress update since {$since}"));
            }
        }

        if ($user?->isManager()) {
            $pendingLeave = LeaveRequest::query()->pending()->where('user_id', '!=', $user->id)->count();
            if ($pendingLeave > 0) {
                $items->push($this->linkItem('warning', 'heroicon-m-calendar-days', "{$pendingLeave} leave request(s) awaiting approval", 'Leave', LeaveRequestResource::getUrl('index')));
            }

            $slaBreached = AuthoritySubmission::query()->slaBreached()->count();
            if ($slaBreached > 0) {
                $items->push($this->linkItem('danger', 'heroicon-m-building-library', "{$slaBreached} authority submission(s) past SLA", 'Authority', AuthoritySubmissionResource::getUrl('index')));
            }
        }

        $items = $items->sortBy(fn (array $item) => static::SEVERITY[$item['tone']])->values();

        return [
            'items' => $items->take(8),
            'more' => max(0, $items->count() - 8),
            'counts' => $items->countBy('tone'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function projectItem(Project $project, string $tone, string $icon, string $text): array
    {
        return [
            'tone' => $tone,
            'icon' => $icon,
            'tag' => $project->project_code,
            'title' => $project->title,
            'text' => $text,
            'projectId' => $project->id,
            'url' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function linkItem(string $tone, string $icon, string $text, string $tag, string $url): array
    {
        return [
            'tone' => $tone,
            'icon' => $icon,
            'tag' => $tag,
            'title' => null,
            'text' => $text,
            'projectId' => null,
            'url' => $url,
        ];
    }
}
