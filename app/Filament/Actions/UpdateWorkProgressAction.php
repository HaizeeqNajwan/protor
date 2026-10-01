<?php

namespace App\Filament\Actions;

use App\Enums\TaskStatus;
use App\Models\ProjectTask;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/** Staff on the project record progress (0–100%) on a work, with a note. Managers never can. */
class UpdateWorkProgressAction
{
    public static function make(): Action
    {
        return Action::make('updateProgress')
            ->label('Update progress')
            ->icon('heroicon-o-chart-bar')
            ->color('primary')
            ->button()
            ->size('sm')
            ->modalWidth('lg')
            ->modalHeading(fn (ProjectTask $record) => $record->title)
            ->modalDescription(fn (ProjectTask $record) => "{$record->project?->project_code} · {$record->stage?->getLabel()}")
            ->visible(fn (ProjectTask $record) => $record->canUpdateProgress(auth()->user()))
            ->authorize(fn (ProjectTask $record) => $record->canUpdateProgress(auth()->user()))
            ->fillForm(fn (ProjectTask $record) => ['progress_percentage' => $record->progress_percentage])
            ->schema([
                TextInput::make('progress_percentage')
                    ->label('Progress')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(100)
                    ->step(5)
                    ->suffix('%')
                    ->required()
                    ->helperText('100% marks the work as completed.'),
                Textarea::make('note')
                    ->label('What was done / blockers')
                    ->rows(3),
            ])
            ->action(function (ProjectTask $record, array $data) {
                $record->recordProgress(auth()->user(), (int) $data['progress_percentage'], $data['note'] ?? null);

                Notification::make()
                    ->title('Progress updated to '.$record->progress_percentage.'%')
                    ->body($record->status === TaskStatus::Completed ? 'Work completed.' : null)
                    ->success()
                    ->send();
            });
    }
}
