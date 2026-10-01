<?php

namespace App\Filament\Resources\LeaveRequestResource\Pages;

use App\Enums\LeaveStatus;
use App\Filament\Resources\LeaveRequestResource;
use App\Models\LeaveRequest;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListLeaveRequests extends ListRecords
{
    protected static string $resource = LeaveRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Apply for Leave'),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('All'),
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->pending()),
            'approved' => Tab::make('Approved')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', LeaveStatus::Approved)),
        ];

        if (auth()->user()?->isManager()) {
            $tabs['mine'] = Tab::make('My Leave')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('user_id', auth()->id()));
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        $hasPendingForMe = auth()->user()?->isManager()
            && LeaveRequest::query()->pending()->where('user_id', '!=', auth()->id())->exists();

        return $hasPendingForMe ? 'pending' : 'all';
    }
}
