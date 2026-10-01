<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * Approving / regulatory authorities commonly dealt with in Kuching and Sarawak.
 *
 * NOTE: defaultSlaDays() values are PLACEHOLDERS for planning and alerting only.
 * They are not official statutory timelines. Adjust them to your firm's actual
 * experience with each authority before relying on the SLA badges.
 */
enum AuthorityName: string implements HasColor, HasDescription, HasLabel
{
    case DBKU = 'DBKU';
    case MBKS = 'MBKS';
    case MPP = 'MPP';
    case MPPJ = 'MPPJ';
    case BOMBA = 'BOMBA';
    case KWB = 'KWB';
    case JBALB = 'JBALB';
    case SESCO = 'SESCO';
    case SSD = 'SSD';
    case SPA = 'SPA';
    case LandAndSurvey = 'L&S';
    case JKR = 'JKR';

    public function getLabel(): string
    {
        return $this->value;
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::DBKU => 'Dewan Bandaraya Kuching Utara',
            self::MBKS => 'Majlis Bandaraya Kuching Selatan',
            self::MPP => 'Majlis Perbandaran Padawan',
            self::MPPJ => 'MPPJ', // TODO: confirm full name used by your firm
            self::BOMBA => 'Jabatan Bomba dan Penyelamat Malaysia',
            self::KWB => 'Kuching Water Board',
            self::JBALB => 'Jabatan Bekalan Air Luar Bandar',
            self::SESCO => 'Sarawak Energy (SESCO)',
            self::SSD => 'Sewerage Services Department',
            self::SPA => 'State Planning Authority',
            self::LandAndSurvey => 'Land and Survey Department Sarawak',
            self::JKR => 'Jabatan Kerja Raya Sarawak',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DBKU, self::MBKS, self::MPP, self::MPPJ => 'primary',
            self::BOMBA => 'danger',
            self::KWB, self::JBALB, self::SSD => 'info',
            self::SESCO => 'warning',
            self::SPA, self::LandAndSurvey => 'success',
            self::JKR => 'gray',
        };
    }

    /** Placeholder turnaround (calendar days) used to compute the expected response date. */
    public function defaultSlaDays(): int
    {
        return match ($this) {
            self::DBKU, self::MBKS, self::MPP, self::MPPJ => 30,
            self::BOMBA => 21,
            self::KWB, self::JBALB, self::SSD => 21,
            self::SESCO => 14,
            self::SPA => 60,
            self::LandAndSurvey => 45,
            self::JKR => 30,
        };
    }

    /** Options with full names, for selects. */
    public static function optionsWithDescriptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $a) => [$a->value => "{$a->value} — {$a->getDescription()}"])
            ->all();
    }
}
