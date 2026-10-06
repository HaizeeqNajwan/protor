<?php

namespace App\Filament\Resources;

use App\Enums\EngineeringDiscipline;
use App\Enums\ProjectStage;
use App\Enums\ProjectStatus;
use App\Filament\Resources\ProjectResource\Pages;
use App\Models\Project;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static string|UnitEnum|null $navigationGroup = 'Projects';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

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
     | Authorization (Shield policy AND role rule must both pass)
     |--------------------------------------------------------------------- */

    public static function canCreate(): bool
    {
        return parent::canCreate() && static::isManager();
    }

    public static function canEdit(Model $record): bool
    {
        return parent::canEdit($record) && static::isManager();
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && static::isManager();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->visibleTo(static::user());
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['project_code', 'title', 'client_name', 'location'];
    }

    /* ---------------------------------------------------------------------
     | Form
     |--------------------------------------------------------------------- */

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Project Details')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('project_code')
                        ->label('Project Code')
                        ->placeholder('PRT-2026-001')
                        ->required()
                        ->maxLength(30)
                        ->unique(ignoreRecord: true),
                    TextInput::make('title')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('client_name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('client_contact')
                        ->label('Client Contact (name / phone / email)')
                        ->maxLength(255),
                    TextInput::make('location')
                        ->placeholder('e.g. Jalan Stutong, Kuching')
                        ->maxLength(255),
                    Select::make('discipline')
                        ->options(EngineeringDiscipline::class)
                        ->required()
                        ->native(false),
                    Select::make('status')
                        ->options(ProjectStatus::class)
                        ->default(ProjectStatus::Active)
                        ->required()
                        ->native(false),
                    ToggleButtons::make('current_stage')
                        ->label('Current stage')
                        ->options(ProjectStage::class)
                        ->default(ProjectStage::PreDesign)
                        ->inline()
                        ->required()
                        ->helperText('Switch freely between stages at any time until the project is completed.')
                        ->columnSpanFull(),
                    Select::make('project_manager_id')
                        ->label('Project Manager')
                        ->relationship(
                            'projectManager',
                            'name',
                            fn (Builder $query) => $query->whereHas('roles', fn (Builder $r) => $r->whereIn('name', [
                                User::ROLE_PROJECT_MANAGER,
                                User::ROLE_SUPER_ADMIN,
                            ])),
                        )
                        ->default(fn () => static::user()?->id)
                        ->searchable()
                        ->preload()
                        ->required(),
                    Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Section::make('Assigned Staff')
                ->description('The staff on this project. They see it on their dashboard, update the progress of its works and can switch its stage.')
                ->icon('heroicon-o-user-group')
                ->columnSpanFull()
                ->schema([
                    Select::make('members')
                        ->label('Staff')
                        ->relationship(
                            'members',
                            'name',
                            fn (Builder $query) => $query->whereHas('roles', fn (Builder $r) => $r->where('name', User::ROLE_STAFF_ENGINEER)),
                        )
                        ->multiple()
                        ->searchable()
                        ->preload(),
                ]),

            Section::make('Schedule')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    DatePicker::make('start_date')
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                    DatePicker::make('target_completion_date')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->afterOrEqual('start_date'),
                    DatePicker::make('actual_completion_date')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->visibleOn(['edit', 'view']),
                ]),

            Section::make('Reference Data')
                ->columnSpanFull()
                ->description('Lot no., title no., SPA / DBKU file refs, etc. Stored as jsonb.')
                ->collapsed()
                ->schema([
                    KeyValue::make('metadata')
                        ->keyLabel('Field')
                        ->valueLabel('Value')
                        ->reorderable(),
                ]),
        ]);
    }

    /* ---------------------------------------------------------------------
     | Table
     |--------------------------------------------------------------------- */

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withStageData()
                ->withAvg('tasks', 'progress_percentage')
                ->withCount(['tasks as open_tasks_count' => fn (Builder $q) => $q->open()]))
            ->columns([
                TextColumn::make('project_code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->description(fn (Project $record) => $record->discipline?->getLabel())
                    ->copyable(),
                TextColumn::make('title')
                    ->searchable()
                    ->limit(34)
                    ->description(fn (Project $record) => $record->client_name),
                TextColumn::make('discipline')
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('current_stage')
                    ->label('Stage')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('projectManager.name')
                    ->label('PM')
                    ->sortable()
                    ->toggleable(),
                ViewColumn::make('tasks_avg_progress_percentage')
                    ->label('Stage Progress')
                    ->view('filament.columns.stage-track')
                    ->sortable(),
                TextColumn::make('schedule_health')
                    ->label('Health')
                    ->badge()
                    ->state(fn (Project $record) => $record->scheduleHealth())
                    ->formatStateUsing(fn (string $state) => str($state)->replace('_', ' ')->title())
                    ->color(fn (string $state) => match ($state) {
                        'overdue', 'behind' => 'danger',
                        'at_risk' => 'warning',
                        'on_track', 'completed' => 'success',
                        default => 'gray',
                    })
                    ->tooltip(fn (Project $record) => $record->scheduleVariance() === null
                        ? 'Set start and target dates to track schedule health'
                        : sprintf('%+d%% vs plan', round($record->scheduleVariance()))),
                TextColumn::make('open_tasks_count')
                    ->label('Open Works')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('target_completion_date')
                    ->label('Target')
                    ->date('d M Y')
                    ->sortable()
                    ->color(fn (Project $record) => ($record->target_completion_date?->isPast()
                        && $record->status !== ProjectStatus::Completed) ? 'danger' : null)
                    ->icon(fn (Project $record) => ($record->target_completion_date?->isPast()
                        && $record->status !== ProjectStatus::Completed) ? 'heroicon-m-exclamation-triangle' : null),
                TextColumn::make('location')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('target_completion_date')
            ->filters([
                SelectFilter::make('current_stage')
                    ->label('Stage')
                    ->options(ProjectStage::class)
                    ->multiple(),
                SelectFilter::make('status')
                    ->options(ProjectStatus::class)
                    ->multiple(),
                SelectFilter::make('discipline')
                    ->options(EngineeringDiscipline::class)
                    ->multiple(),
                SelectFilter::make('project_manager_id')
                    ->label('Project Manager')
                    ->relationship('projectManager', 'name')
                    ->visible(fn () => static::isManager()),
                Filter::make('overdue')
                    ->label('Past target completion')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->overdue()),
                Filter::make('my_projects')
                    ->label('Projects I manage')
                    ->toggle()
                    ->visible(fn () => static::isManager())
                    ->query(fn (Builder $query) => $query->where('project_manager_id', static::user()?->id)),
                TrashedFilter::make()
                    ->visible(fn () => static::isManager()),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    static::switchStageAction(),
                    static::completeProjectAction(),
                    static::reopenProjectAction(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ])->visible(fn () => static::isManager()),
            ]);
    }

    /** Move a project to any stage — forwards or backwards — until it is completed. */
    public static function switchStageAction(): Action
    {
        return Action::make('switchStage')
            ->label('Switch stage')
            ->icon('heroicon-o-arrows-right-left')
            ->color('gray')
            ->visible(fn (Project $record) => $record->canSwitchStage(static::user()))
            ->fillForm(fn (Project $record) => ['current_stage' => $record->currentStage()])
            ->modalWidth('lg')
            ->schema([
                ToggleButtons::make('current_stage')
                    ->label('Move project to')
                    ->options(ProjectStage::class)
                    ->required()
                    ->inline(),
            ])
            ->after(fn ($livewire) => $livewire->dispatch('project-stage-switched'))
            ->action(function (Project $record, array $data) {
                $record->switchStage(ProjectStage::from($data['current_stage'] instanceof ProjectStage ? $data['current_stage']->value : $data['current_stage']));

                Notification::make()
                    ->title("{$record->project_code} is now in {$record->currentStage()->getLabel()}")
                    ->success()
                    ->send();
            });
    }

    /** Shown to the team and managers once every work is at 100%. */
    public static function completeProjectAction(): Action
    {
        return Action::make('completeProject')
            ->label('Complete project')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (Project $record) => $record->canComplete(static::user()))
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-check-badge')
            ->modalHeading(fn (Project $record) => "Complete {$record->project_code}?")
            ->modalDescription('All works are at 100%. The project will be marked as completed today.')
            ->modalSubmitActionLabel('Mark as completed')
            ->after(fn ($livewire) => $livewire->dispatch('project-stage-switched'))
            ->action(function (Project $record) {
                $record->markCompleted(static::user());

                Notification::make()->title("{$record->project_code} completed")->success()->send();
            });
    }

    public static function reopenProjectAction(): Action
    {
        return Action::make('reopenProject')
            ->label('Reopen')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->visible(fn (Project $record) => $record->canReopen(static::user()))
            ->requiresConfirmation()
            ->after(fn ($livewire) => $livewire->dispatch('project-stage-switched'))
            ->action(fn (Project $record) => $record->reopen());
    }

    public static function getRelations(): array
    {
        return [
            ProjectResource\RelationManagers\WorksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'view' => Pages\ViewProject::route('/{record}'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
        ];
    }
}
