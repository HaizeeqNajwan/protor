<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TaskPriority: string implements HasColor, HasIcon, HasLabel
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Medium => 'info',
            self::High => 'warning',
            self::Urgent => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Low => 'heroicon-m-arrow-down',
            self::Medium => 'heroicon-m-minus',
            self::High => 'heroicon-m-arrow-up',
            self::Urgent => 'heroicon-m-fire',
        };
    }

    /** Weight used for workload scoring (Urgent counts heavier). */
    public function weight(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Urgent => 5,
        };
    }
}
