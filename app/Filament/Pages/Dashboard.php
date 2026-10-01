<?php

namespace App\Filament\Pages;

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
            'xl' => 3,
        ];
    }

    public function getSubheading(): string|Htmlable|null
    {
        $hour = (int) now()->format('G');

        $greeting = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };

        $name = str(auth()->user()?->name ?? '')->before(' ');

        return sprintf(
            '%s, %s · %s · Week %s',
            $greeting,
            $name,
            now()->format('l, j F Y'),
            now()->isoWeek(),
        );
    }
}
