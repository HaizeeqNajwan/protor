<?php

namespace App\Filament\Resources;

use App\Enums\LeaveDayPart;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Filament\Resources\LeaveRequestResource\Pages;
use App\Models\LeaveRequest;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class LeaveRequestResource extends Resource
{
    protected static ?string $model = LeaveRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Leave Requests';

    /* ---------------------------------------------------------------------
     | Role helpers
     |--------------------------------------------------------------------- */

    protected static function user(): ?User
    {
        return auth()->user();
    }

    protected static function isManager(): bool
    {
        return (bool) static::user()?->isManager();
    }

    /* ---------------------------------------------------------------------
     | Authorization + scoping
     |--------------------------------------------------------------------- */

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(static::user());
    }

    /** Only pending requests can be edited — by the applicant or a manager. */
    public static function canEdit(Model $record): bool
    {
        /** @var LeaveRequest $record */
        return parent::canEdit($record)
            && $record->status === LeaveStatus::Pending
            && (static::isManager() || (int) $record->user_id === (int) static::user()?->id);
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && (bool) static::user()?->isSuperAdmin();
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::isManager()) {
            return null;
        }

        $pending = LeaveRequest::query()
            ->pending()
            ->where('user_id', '!=', static::user()?->id)
            ->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Leave requests awaiting your approval';
    }

    /* ---------------------------------------------------------------------
     | Shared bits
     |--------------------------------------------------------------------- */

    protected static function notifyApplicant(LeaveRequest $record): void
    {
        $status = $record->status;

        Notification::make()
            ->title("Your leave ({$record->start_date->format('d M')} – {$record->end_date->format('d M')}) was {$status->getLabel()}")
            ->body($record->decision_remarks)
            ->color($status->getColor())
            ->icon($status->getIcon())
            ->sendToDatabase($record->user);
    }

    protected static function clashSummary(LeaveRequest $record): string
    {
        $projects = $record->clashingProjects();

        $summary = "{$record->user->name} · {$record->leave_type->getLabel()} · "
            ."{$record->start_date->format('d M Y')}"
            .($record->half_day ? " ({$record->day_part->getLabel()})" : " – {$record->end_date->format('d M Y')}")
            ." · {$record->days} working day(s).";

        if ($projects->isEmpty()) {
            return $summary.' No project target dates fall within this period.';
        }

        $list = $projects->map(fn ($p) => "{$p->project_code} (target {$p->target_completion_date->format('d M')})")->implode(', ');

        return $summary." ⚠ Project target date(s) during leave: {$list}.";
    }

    /* ---------------------------------------------------------------------
     | Form helpers
     |--------------------------------------------------------------------- */

    protected static function isHalfDay(mixed $dayPart): bool
    {
        $dayPart = $dayPart instanceof LeaveDayPart ? $dayPart : LeaveDayPart::tryFrom((string) $dayPart);

        return (bool) $dayPart?->isHalfDay();
    }

    protected static function daysHint(Get $get): ?string
    {
        $halfDay = static::isHalfDay($get('day_part'));
        $days = LeaveRequest::countWorkingDays($get('start_date'), $halfDay ? $get('start_date') : $get('end_date'), $halfDay);

        return $days > 0 ? "{$days} working day(s), excl. weekends (public holidays not deducted)." : null;
    }

    /* ---------------------------------------------------------------------
     | Form
     |--------------------------------------------------------------------- */

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Leave Application')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Select::make('user_id')
                        ->label('Staff')
                        ->relationship('user', 'name')
                        ->default(fn () => static::user()?->id)
                        ->searchable()
                        ->preload()
                        ->required()
                        ->visible(fn () => static::isManager())
                        ->helperText('Managers may file leave on behalf of staff.'),
                    Select::make('leave_type')
                        ->options(LeaveType::class)
                        ->default(LeaveType::Annual)
                        ->required()
                        ->native(false),
                    ToggleButtons::make('day_part')
                        ->label('Duration')
                        ->options(LeaveDayPart::class)
                        ->default(LeaveDayPart::Full)
                        ->inline()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set) {
                            if (static::isHalfDay($get('day_part'))) {
                                $set('end_date', $get('start_date'));
                            }
                        })
                        ->columnSpanFull(),
                    DatePicker::make('start_date')
                        ->label(fn (Get $get) => static::isHalfDay($get('day_part')) ? 'Date' : 'Start date')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set, $state) {
                            if (static::isHalfDay($get('day_part'))) {
                                $set('end_date', $state);
                            }
                        })
                        ->helperText(fn (Get $get) => static::isHalfDay($get('day_part')) ? static::daysHint($get) : null)
                        ->rule(fn (Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                            $halfDay = static::isHalfDay($get('day_part'));
                            $end = $halfDay ? $value : $get('end_date');

                            if ($value && $end && LeaveRequest::countWorkingDays($value, $end, $halfDay) <= 0) {
                                $fail('The selected date(s) fall on a weekend — pick at least one working day.');
                            }
                        }),
                    DatePicker::make('end_date')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->required()
                        ->afterOrEqual('start_date')
                        ->live()
                        ->hidden(fn (Get $get) => static::isHalfDay($get('day_part')))
                        ->dehydratedWhenHidden()
                        ->helperText(fn (Get $get) => static::daysHint($get)),
                    Textarea::make('reason')
                        ->rows(2)
                        ->required(fn (Get $get) => in_array($get('leave_type'), [LeaveType::Emergency, LeaveType::Emergency->value, LeaveType::Unpaid, LeaveType::Unpaid->value], true))
                        ->columnSpanFull(),
                    Textarea::make('handover_notes')
                        ->label('Handover Notes')
                        ->placeholder('Ongoing submissions, site visits, pending replies to authorities…')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Section::make('Decision')
                ->columnSpanFull()
                ->columns(3)
                ->visibleOn(['edit', 'view'])
                ->schema([
                    Select::make('status')
                        ->options(LeaveStatus::class)
                        ->disabled()
                        ->dehydrated(false),
                    Select::make('approver_id')
                        ->label('Decided By')
                        ->relationship('approver', 'name')
                        ->disabled()
                        ->dehydrated(false),
                    DatePicker::make('decided_at')
                        ->disabled()
                        ->dehydrated(false),
                    Textarea::make('decision_remarks')
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /* ---------------------------------------------------------------------
     | Table
     |--------------------------------------------------------------------- */

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user:id,name', 'approver:id,name']))
            ->columns([
                TextColumn::make('user.name')
                    ->label('Staff')
                    ->searchable()
                    ->sortable()
                    ->visible(fn () => static::isManager()),
                TextColumn::make('leave_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('start_date')
                    ->label('From')
                    ->date('D, d M Y')
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label('To')
                    ->date('D, d M Y')
                    ->sortable(),
                TextColumn::make('days')
                    ->numeric(1)
                    ->alignCenter()
                    ->badge()
                    ->color(fn (LeaveRequest $record) => $record->half_day ? 'info' : 'gray')
                    ->description(fn (LeaveRequest $record) => $record->half_day ? $record->day_part->getLabel() : null),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('approver.name')
                    ->label('Decided By')
                    ->placeholder('—')
                    ->description(fn (LeaveRequest $record) => $record->decided_at?->format('d M Y H:i'))
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Applied')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('start_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(LeaveStatus::class)
                    ->multiple(),
                SelectFilter::make('leave_type')
                    ->label('Type')
                    ->options(LeaveType::class)
                    ->multiple(),
                SelectFilter::make('user_id')
                    ->label('Staff')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => static::isManager()),
                Filter::make('awaiting_me')
                    ->label('Awaiting my approval')
                    ->toggle()
                    ->visible(fn () => static::isManager())
                    ->query(fn (Builder $query) => $query->pending()->where('user_id', '!=', static::user()?->id)),
                Filter::make('on_leave_today')
                    ->label('On leave today')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->where('status', LeaveStatus::Approved)->covering(today())),
                Filter::make('upcoming')
                    ->label('Upcoming (next 30 days)')
                    ->toggle()
                    ->query(fn (Builder $query) => $query
                        ->whereIn('status', [LeaveStatus::Pending, LeaveStatus::Approved])
                        ->whereBetween('start_date', [today(), today()->addDays(30)])),
            ])
            ->recordActions([
                // One-click PM approval (with a clash check in the confirmation modal).
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->button()
                    ->size('sm')
                    ->requiresConfirmation()
                    ->modalHeading('Approve leave?')
                    ->modalDescription(fn (LeaveRequest $record) => static::clashSummary($record))
                    ->modalSubmitActionLabel('Approve')
                    ->visible(fn (LeaveRequest $record) => $record->canBeDecidedBy(static::user()))
                    ->action(function (LeaveRequest $record) {
                        $record->approve(static::user());
                        static::notifyApplicant($record);

                        Notification::make()
                            ->title("Leave approved for {$record->user->name}")
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->button()
                    ->outlined()
                    ->size('sm')
                    ->modalHeading('Reject leave request')
                    ->visible(fn (LeaveRequest $record) => $record->canBeDecidedBy(static::user()))
                    ->schema([
                        Textarea::make('remarks')
                            ->label('Reason for rejection')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (LeaveRequest $record, array $data) {
                        $record->reject(static::user(), $data['remarks']);
                        static::notifyApplicant($record);

                        Notification::make()
                            ->title("Leave rejected for {$record->user->name}")
                            ->danger()
                            ->send();
                    }),
                ActionGroup::make([
                    Action::make('cancel')
                        ->label('Cancel Request')
                        ->icon('heroicon-o-minus-circle')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->visible(fn (LeaveRequest $record) => (int) $record->user_id === (int) static::user()?->id
                            && ($record->status === LeaveStatus::Pending
                                || ($record->status === LeaveStatus::Approved && $record->start_date->isFuture())))
                        ->action(fn (LeaveRequest $record) => $record->update(['status' => LeaveStatus::Cancelled])),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkApprove')
                        ->label('Approve selected')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $approved = 0;

                            foreach ($records as $record) {
                                if ($record->canBeDecidedBy(static::user())) {
                                    $record->approve(static::user());
                                    static::notifyApplicant($record);
                                    $approved++;
                                }
                            }

                            Notification::make()
                                ->title("{$approved} leave request(s) approved")
                                ->body($approved < $records->count() ? 'Some were skipped (not pending, or your own request).' : null)
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ])->visible(fn () => static::isManager()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeaveRequests::route('/'),
            'create' => Pages\CreateLeaveRequest::route('/create'),
            'edit' => Pages\EditLeaveRequest::route('/{record}/edit'),
        ];
    }
}
