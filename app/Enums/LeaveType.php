<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Supporting enum — leave categories. */
enum LeaveType: string implements HasColor, HasLabel
{
    case Annual = 'annual';
    case Medical = 'medical';
    case Emergency = 'emergency';
    case Replacement = 'replacement';
    case Unpaid = 'unpaid';

    public function getLabel(): string
    {
        return match ($this) {
            self::Annual => 'Annual Leave',
            self::Medical => 'Medical Leave',
            self::Emergency => 'Emergency Leave',
            self::Replacement => 'Replacement Leave',
            self::Unpaid => 'Unpaid Leave',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Annual => 'info',
            self::Medical => 'danger',
            self::Emergency => 'warning',
            self::Replacement => 'success',
            self::Unpaid => 'gray',
        };
    }
}
