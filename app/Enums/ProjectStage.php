<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/** The three delivery stages every project moves through. Tasks belong to one stage. */
enum ProjectStage: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case PreDesign = 'pre_design';
    case Design = 'design';
    case PostDesign = 'post_design';

    public function getLabel(): string
    {
        return match ($this) {
            self::PreDesign => 'Pre-Design',
            self::Design => 'Design',
            self::PostDesign => 'Post-Design',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PreDesign => 'info',
            self::Design => 'primary',
            self::PostDesign => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PreDesign => 'heroicon-m-magnifying-glass-circle',
            self::Design => 'heroicon-m-pencil-square',
            self::PostDesign => 'heroicon-m-wrench-screwdriver',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::PreDesign => 'Brief, site survey, feasibility, proposal',
            self::Design => 'Calculations, drawings, authority submission',
            self::PostDesign => 'Construction supervision, inspection, handover',
        };
    }

    /** Short code shown on the stage pipeline (e.g. "S1"). */
    public function code(): string
    {
        return 'S'.($this->order() + 1);
    }

    public function order(): int
    {
        return array_search($this, self::cases(), true);
    }
}
