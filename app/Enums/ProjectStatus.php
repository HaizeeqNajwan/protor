<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Project lifecycle. Where the project is in delivery lives in ProjectStage
 * (projects.current_stage), which can be switched freely until Completed.
 */
enum ProjectStatus: string implements HasColor, HasIcon, HasLabel
{
    case Active = 'active';
    case OnHold = 'on_hold';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::OnHold => 'On Hold',
            self::Completed => 'Completed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'primary',
            self::OnHold => 'warning',
            self::Completed => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Active => 'heroicon-m-play-circle',
            self::OnHold => 'heroicon-m-pause-circle',
            self::Completed => 'heroicon-m-check-badge',
        };
    }

    public static function active(): array
    {
        return [self::Active];
    }
}
