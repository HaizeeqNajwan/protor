<?php

namespace App\Filament\Pages;

use App\Models\Project;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Operations Center';

    protected static ?string $navigationLabel = 'Dashboard';

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'xl' => 12,
        ];
    }

    public function getHeading(): string|Htmlable
    {
        $hour = (int) now()->format('G');
        $greeting = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };

        return $greeting.', '.str(auth()->user()?->name ?? '')->before(' ');
    }

    public function getSubheading(): string|Htmlable|null
    {
        $user = auth()->user();
        $live = Project::query()->visibleTo($user)->active()->count();
        $late = Project::query()->visibleTo($user)->overdue()->count();

        $summary = $user?->isManager()
            ? sprintf('%d live %s', $live, str('project')->plural($live))
            : sprintf('%d %s on your plate', $live, str('project')->plural($live));

        if ($late > 0) {
            $summary .= sprintf(' · %d past target date', $late);
        }

        return sprintf('%s · Week %s · %s', now()->format('l, j F Y'), now()->isoWeek(), $summary);
    }
}
