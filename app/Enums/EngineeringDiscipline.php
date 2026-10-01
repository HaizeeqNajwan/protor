<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum EngineeringDiscipline: string implements HasColor, HasIcon, HasLabel
{
    case Civil = 'civil';
    case Structural = 'structural';
    case Mechanical = 'mechanical';
    case Electrical = 'electrical';
    case Infrastructure = 'infrastructure';
    case Multidisciplinary = 'multidisciplinary';

    public function getLabel(): string
    {
        return match ($this) {
            self::Civil => 'Civil',
            self::Structural => 'Structural',
            self::Mechanical => 'Mechanical',
            self::Electrical => 'Electrical',
            self::Infrastructure => 'Infrastructure',
            self::Multidisciplinary => 'Multidisciplinary',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Civil => 'info',
            self::Structural => 'primary',
            self::Mechanical => 'warning',
            self::Electrical => 'danger',
            self::Infrastructure => 'success',
            self::Multidisciplinary => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Civil => 'heroicon-m-map',
            self::Structural => 'heroicon-m-building-office-2',
            self::Mechanical => 'heroicon-m-cog-6-tooth',
            self::Electrical => 'heroicon-m-bolt',
            self::Infrastructure => 'heroicon-m-truck',
            self::Multidisciplinary => 'heroicon-m-squares-2x2',
        };
    }

    /** C&S vs M&E grouping used in reports. */
    public function group(): string
    {
        return match ($this) {
            self::Civil, self::Structural, self::Infrastructure => 'C&S',
            self::Mechanical, self::Electrical => 'M&E',
            self::Multidisciplinary => 'C&S + M&E',
        };
    }
}
