<?php

namespace App\Filament\Resources\AuthoritySubmissionResource\Pages;

use App\Enums\SubmissionStatus;
use App\Filament\Resources\AuthoritySubmissionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAuthoritySubmissions extends ListRecords
{
    protected static string $resource = AuthoritySubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New Submission'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'awaiting' => Tab::make('Awaiting Authority')
                ->modifyQueryUsing(fn (Builder $query) => $query->awaitingAuthority()),
            'queries' => Tab::make('Queries to Answer')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', SubmissionStatus::QueryRaised)),
            'overdue' => Tab::make('SLA Breached')
                ->modifyQueryUsing(fn (Builder $query) => $query->slaBreached()),
        ];
    }
}
