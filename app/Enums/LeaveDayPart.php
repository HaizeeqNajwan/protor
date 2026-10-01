<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/** Full day(s), or a half day taken in the morning or afternoon. */
enum LeaveDayPart: string implements HasIcon, HasLabel
{
    case Full = 'full';
    case Morning = 'am';
    case Afternoon = 'pm';

    public function getLabel(): string
    {
        return match ($this) {
            self::Full => 'Full day(s)',
            self::Morning => 'Half day · AM',
            self::Afternoon => 'Half day · PM',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Full => 'heroicon-m-calendar-days',
            self::Morning => 'heroicon-m-sun',
            self::Afternoon => 'heroicon-m-moon',
        };
    }

    public function isHalfDay(): bool
    {
        return $this !== self::Full;
    }
}
