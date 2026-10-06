<?php

namespace App\Filament\Widgets;

use App\Enums\LeaveStatus;
use App\Enums\ProjectStatus;
use App\Models\LeaveRequest;
use App\Models\Project;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

/** Manager view of the staff: how many live projects each has, and who is away today. */
class TeamWidget extends Widget
{
    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = ['default' => 1, 'xl' => 4];

    protected string $view = 'filament.widgets.team-widget';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isManager();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $staff = User::query()
            ->whereHas('roles', fn (Builder $r) => $r->where('name', User::ROLE_STAFF_ENGINEER))
            ->with(['projects' => fn ($p) => $p
                ->where('status', '!=', ProjectStatus::Completed)
                ->with(['tasks:id,project_id,progress_percentage'])])
            ->orderBy('name')
            ->get();

        $leaveToday = LeaveRequest::query()
            ->where('status', LeaveStatus::Approved)
            ->covering(today())
            ->get()
            ->keyBy('user_id');

        $rows = $staff->map(fn (User $user) => [
            'name' => $user->name,
            'projects' => $user->projects->count(),
            'progress' => $user->projects->isEmpty() ? null : round((float) $user->projects->avg(fn (Project $p) => $p->overallProgress())),
            'leave' => $leaveToday->get($user->id),
        ])->sortByDesc('projects')->values();

        return [
            'rows' => $rows,
            'onLeave' => $leaveToday->count(),
            'maxProjects' => max(1, (int) $rows->max('projects')),
        ];
    }
}
