<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TaskStatus: string implements HasColor, HasIcon, HasLabel
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case ReviewPending = 'review_pending';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::InProgress => 'In Progress',
            self::ReviewPending => 'Review Pending',
            self::Completed => 'Completed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NotStarted => 'gray',
            self::InProgress => 'info',
            self::ReviewPending => 'warning',
            self::Completed => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::NotStarted => 'heroicon-m-pause-circle',
            self::InProgress => 'heroicon-m-arrow-path',
            self::ReviewPending => 'heroicon-m-eye',
            self::Completed => 'heroicon-m-check-circle',
        };
    }

    /** Statuses that still count as open work. */
    public static function open(): array
    {
        return [self::NotStarted, self::InProgress, self::ReviewPending];
    }
}
