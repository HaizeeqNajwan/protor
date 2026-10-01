<?php

namespace App\Filament\Resources;

use App\Enums\AuthorityName;
use App\Enums\SubmissionStatus;
use App\Filament\Resources\AuthoritySubmissionResource\Pages;
use App\Models\AuthoritySubmission;
use App\Models\Project;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;
use UnitEnum;

class AuthoritySubmissionResource extends Resource
{
    protected static ?string $model = AuthoritySubmission::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static string|UnitEnum|null $navigationGroup = 'Projects';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Authority Submissions';

    protected static ?string $recordTitleAttribute = 'reference_no';

    /** Common submission types — free text is still allowed. */
    public const SUBMISSION_TYPES = [
        'Planning Permission',
        'Building Plan',
        'Earthworks Plan',
        'Road & Drainage Plan',
        'Structural Plan',
        'Fire Safety (BOMBA)',
        'Water Reticulation',
        'Sewerage Plan',
        'Electrical Supply Application',
        'Subdivision / Survey',
        'Occupation Permit (OP)',
        'Temporary Occupation Permit (TOP)',
    ];

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
     | Authorization + query scoping
     |--------------------------------------------------------------------- */

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->visibleTo(static::user());
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && static::isManager();
    }

    public static function getNavigationBadge(): ?string
    {
        $breached = static::getEloquentQuery()->slaBreached()->count();

        return $breached > 0 ? (string) $breached : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Submissions past expected authority response date';
    }

    /* ---------------------------------------------------------------------
     | Form
     |--------------------------------------------------------------------- */

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Submission')
                ->columns(2)
                ->schema([
                    Select::make('project_id')
                        ->label('Project')
                        ->relationship('project', 'title', fn (Builder $query) => $query->visibleTo(static::user()))
                        ->getOptionLabelFromRecordUsing(fn (Project $record) => "{$record->project_code} — {$record->title}")
                        ->searchable(['project_code', 'title'])
                        ->preload()
                        ->required(),
                    Select::make('authority')
                        ->options(AuthorityName::class)
                        ->helperText(function (Get $get) {
                            $state = $get('authority');
                            $authority = $state instanceof AuthorityName ? $state : AuthorityName::tryFrom((string) $state);

                            return $authority?->getDescription();
                        })
                        ->required()
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Set $set, $state) {
                            $authority = $state instanceof AuthorityName ? $state : AuthorityName::tryFrom((string) $state);

                            if ($authority) {
                                $set('sla_days', $authority->defaultSlaDays());
                            }
                        }),
                    TextInput::make('submission_type')
                        ->required()
                        ->datalist(self::SUBMISSION_TYPES)
                        ->maxLength(255),
                    TextInput::make('reference_no')
                        ->label('Authority Reference / File No.')
                        ->maxLength(255),
                    Select::make('status')
                        ->options(SubmissionStatus::class)
                        ->default(SubmissionStatus::Draft)
                        ->required()
                        ->native(false)
                        ->helperText('Use the table actions to log queries / resubmissions so the SLA clock is tracked correctly.'),
                    Textarea::make('description')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            Section::make('SLA Tracking')
                ->columns(4)
                ->schema([
                    DatePicker::make('submitted_at')
                        ->label('Submitted On')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->live()
                        ->maxDate(today()),
                    TextInput::make('sla_days')
                        ->label('SLA (calendar days)')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->default(30)
                        ->required()
                        ->live(onBlur: true),
                    DatePicker::make('resubmitted_at')
                        ->label('Last Resubmitted')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->visibleOn(['edit', 'view']),
                    TextInput::make('expected_response_preview')
                        ->label('Expected Response')
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder(function (Get $get) {
                            $start = $get('resubmitted_at') ?: $get('submitted_at');

                            return $start
                                ? Carbon::parse($start)->addDays((int) $get('sla_days'))->format('d M Y')
                                : '— (set submission date)';
                        }),
                ]),

            Section::make('Outcome')
                ->columns(2)
                ->visibleOn(['edit', 'view'])
                ->schema([
                    DatePicker::make('approved_at')
                        ->label('Approved On')
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                    TextInput::make('approval_ref')
                        ->label('Approval Ref.'),
                    Textarea::make('remarks')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            Section::make('Documents')
                ->collapsed()
                ->schema([
                    FileUpload::make('documents')
                        ->multiple()
                        ->directory('authority-submissions')
                        ->downloadable()
                        ->openable()
                        ->acceptedFileTypes(['application/pdf', 'image/*'])
                        ->maxSize(20480),
                ]),

            Section::make('Authority Query Log')
                ->description('Read-only. Add entries with the "Log Authority Query" action (stored in jsonb).')
                ->visibleOn(['edit', 'view'])
                ->collapsible()
                ->schema([
                    Repeater::make('query_logs')
                        ->hiddenLabel()
                        ->schema([
                            TextInput::make('query_date')->label('Query Date'),
                            TextInput::make('officer'),
                            TextInput::make('reference'),
                            TextInput::make('reply_due')->label('Reply Due'),
                            TextInput::make('status'),
                            Textarea::make('details')->columnSpanFull(),
                            TextInput::make('reply_note')->label('Our Reply')->columnSpanFull(),
                        ])
                        ->columns(5)
                        ->disabled()
                        ->dehydrated(false)
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false),
                ]),
        ]);
    }

    /* ---------------------------------------------------------------------
     | Table
     |--------------------------------------------------------------------- */

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('project:id,project_code,title'))
            ->columns([
                TextColumn::make('project.project_code')
                    ->label('Project')
                    ->searchable()
                    ->sortable()
                    ->tooltip(fn (AuthoritySubmission $record) => $record->project?->title),
                TextColumn::make('authority')
                    ->badge()
                    ->tooltip(fn (AuthoritySubmission $record) => $record->authority?->getDescription())
                    ->sortable(),
                TextColumn::make('submission_type')
                    ->label('Type')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('reference_no')
                    ->label('Ref. No.')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('expected_response_date')
                    ->label('Expected Reply')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('sla_state')
                    ->label('SLA')
                    ->badge()
                    ->state(fn (AuthoritySubmission $record) => $record->sla_state)
                    ->color(fn (string $state) => AuthoritySubmission::slaStateColor($state))
                    ->icon(fn (string $state) => match ($state) {
                        'Overdue' => 'heroicon-m-exclamation-triangle',
                        'Due Soon' => 'heroicon-m-clock',
                        'Awaiting Our Reply' => 'heroicon-m-chat-bubble-left-ellipsis',
                        'On Track' => 'heroicon-m-check-circle',
                        default => null,
                    })
                    ->description(function (AuthoritySubmission $record) {
                        $days = $record->sla_days_remaining;

                        return match (true) {
                            $days === null => null,
                            $days < 0 => abs($days).' day(s) late',
                            default => $days.' day(s) left',
                        };
                    }),
                TextColumn::make('open_queries')
                    ->label('Open Queries')
                    ->state(fn (AuthoritySubmission $record) => $record->openQueriesCount())
                    ->badge()
                    ->color(fn ($state) => (int) $state > 0 ? 'warning' : 'gray')
                    ->alignCenter(),
            ])
            ->defaultSort('expected_response_date')
            ->filters([
                SelectFilter::make('authority')
                    ->options(AuthorityName::class)
                    ->multiple(),
                SelectFilter::make('status')
                    ->options(SubmissionStatus::class)
                    ->multiple(),
                SelectFilter::make('project_id')
                    ->label('Project')
                    ->relationship('project', 'project_code', fn (Builder $query) => $query->visibleTo(static::user()))
                    ->searchable()
                    ->preload(),
                Filter::make('sla_breached')
                    ->label('SLA breached')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->slaBreached()),
                Filter::make('sla_due_soon')
                    ->label('Due within 7 days')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->slaDueSoon()),
                Filter::make('open_only')
                    ->label('Hide closed')
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query) => $query->whereNotIn('status', SubmissionStatus::closed())),
                TrashedFilter::make()
                    ->visible(fn () => static::isManager()),
            ])
            ->recordActions([
                Action::make('logQuery')
                    ->label('Log Authority Query')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('warning')
                    ->modalHeading(fn (AuthoritySubmission $record) => "Log query from {$record->authority?->value}")
                    ->modalDescription('The query is appended to this submission\'s query log and the status becomes "Query Raised" (SLA clock paused).')
                    ->modalSubmitActionLabel('Log Query')
                    ->visible(fn (AuthoritySubmission $record) => static::canEdit($record) && in_array($record->status, [
                        SubmissionStatus::Submitted,
                        SubmissionStatus::Resubmitted,
                        SubmissionStatus::QueryRaised,
                    ], true))
                    ->schema([
                        DatePicker::make('query_date')
                            ->label('Query Received On')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(today())
                            ->maxDate(today())
                            ->required(),
                        TextInput::make('officer')
                            ->label('Authority Officer')
                            ->maxLength(255),
                        TextInput::make('reference')
                            ->label('Query Letter Ref.')
                            ->maxLength(255),
                        Textarea::make('details')
                            ->label('Query Details / Comments')
                            ->rows(4)
                            ->required(),
                        DatePicker::make('reply_due')
                            ->label('Our Reply Due By')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(today()->addDays(14))
                            ->afterOrEqual('query_date'),
                    ])
                    ->action(function (AuthoritySubmission $record, array $data) {
                        $record->logQuery($data, static::user());

                        Notification::make()
                            ->title('Authority query logged')
                            ->body("{$record->openQueriesCount()} open query(ies) on this submission.")
                            ->warning()
                            ->send();
                    }),

                ActionGroup::make([
                    Action::make('recordResubmission')
                        ->label('Record Resubmission')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('info')
                        ->visible(fn (AuthoritySubmission $record) => static::canEdit($record)
                            && $record->status === SubmissionStatus::QueryRaised)
                        ->schema([
                            DatePicker::make('resubmitted_at')
                                ->label('Resubmitted On')
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->default(today())
                                ->maxDate(today())
                                ->required(),
                            Textarea::make('note')
                                ->label('Summary of reply / amendments')
                                ->rows(3),
                        ])
                        ->action(function (AuthoritySubmission $record, array $data) {
                            $record->recordResubmission(
                                Carbon::parse($data['resubmitted_at'])->toDateString(),
                                $data['note'] ?? null,
                                static::user(),
                            );

                            Notification::make()
                                ->title('Resubmission recorded')
                                ->body('SLA restarted. New expected reply: '.$record->expected_response_date?->format('d M Y'))
                                ->success()
                                ->send();
                        }),
                    Action::make('markApproved')
                        ->label('Mark Approved')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(fn (AuthoritySubmission $record) => static::canEdit($record)
                            && ! $record->status?->isClosed()
                            && $record->status !== SubmissionStatus::Draft)
                        ->schema([
                            DatePicker::make('approved_at')
                                ->label('Approval Date')
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->default(today())
                                ->required(),
                            TextInput::make('approval_ref')
                                ->label('Approval Ref.'),
                        ])
                        ->action(function (AuthoritySubmission $record, array $data) {
                            $record->update([
                                'status' => SubmissionStatus::Approved,
                                'approved_at' => $data['approved_at'],
                                'approval_ref' => $data['approval_ref'] ?? null,
                            ]);

                            Notification::make()->title('Submission approved')->success()->send();
                        }),
                    Action::make('markRejected')
                        ->label('Mark Rejected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (AuthoritySubmission $record) => static::isManager()
                            && ! $record->status?->isClosed()
                            && $record->status !== SubmissionStatus::Draft)
                        ->schema([
                            Textarea::make('remarks')->label('Reason')->required(),
                        ])
                        ->action(fn (AuthoritySubmission $record, array $data) => $record->update([
                            'status' => SubmissionStatus::Rejected,
                            'remarks' => $data['remarks'],
                        ])),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ])->visible(fn () => static::isManager()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuthoritySubmissions::route('/'),
            'create' => Pages\CreateAuthoritySubmission::route('/create'),
            'edit' => Pages\EditAuthoritySubmission::route('/{record}/edit'),
        ];
    }
}
