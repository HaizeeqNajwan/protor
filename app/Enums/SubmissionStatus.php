<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Supporting enum — lifecycle of an authority submission. */
enum SubmissionStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case QueryRaised = 'query_raised';
    case Resubmitted = 'resubmitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::QueryRaised => 'Query Raised',
            self::Resubmitted => 'Resubmitted',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Withdrawn => 'Withdrawn',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted, self::Resubmitted => 'info',
            self::QueryRaised => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Withdrawn => 'gray',
        };
    }

    /** Statuses where the authority's SLA clock is running. */
    public static function awaitingAuthority(): array
    {
        return [self::Submitted, self::Resubmitted];
    }

    public static function closed(): array
    {
        return [self::Approved, self::Rejected, self::Withdrawn];
    }

    public function isClosed(): bool
    {
        return in_array($this, self::closed(), true);
    }
}
