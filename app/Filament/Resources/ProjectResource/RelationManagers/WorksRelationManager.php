<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Enums\ProjectStage;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Filament\Actions\UpdateWorkProgressAction;
use App\Models\Project;
use App\Models\ProjectTask;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The project's works, grouped Pre-Design → Design → Post-Design.
 *
 * Works come from the standard templates when the project is created.
 * The staff assigned to the project update progress; managers only
 * tweak the list (extra work, rename, delete).
 */
class WorksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $title = 'Works';

    protected static ?string $modelLabel = 'work';

    protected static ?string $pluralModelLabel = 'works';

    protected static string|BackedEnum|null $icon = 'heroicon-o-squares-2x2';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var Project $ownerRecord */
        $total = $ownerRecord->tasks()->count();
        $done = $ownerRecord->tasks()->where('progress_percentage', '>=', 100)->count();

        return $total > 0 ? "{$done}/{$total}" : null;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->check();
    }

    public function isReadOnly(): bool
    {
        // Staff open projects on the View page and still need "Update progress".
        return false;
    }

    protected function isManager(): bool
    {
        return (bool) auth()->user()?->isManager();
    }

    protected function canManageList(): bool
    {
        return $this->isManager() && $this->getOwnerRecord()->status !== ProjectStatus::Completed;
    }

    /* ---------------------------------------------------------------------
     | Edit form (manager) — list maintenance only, never progress
     |--------------------------------------------------------------------- */

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                ToggleButtons::make('stage')
                    ->options(ProjectStage::class)
                    ->inline()
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('title')
                    ->label('Work')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                DatePicker::make('due_date')
                    ->label('Target date (optional)')
                    ->native(false)
                    ->displayFormat('d/m/Y'),
                Textarea::make('description')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    /* ---------------------------------------------------------------------
     | Table
     |--------------------------------------------------------------------- */

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with('project')
                ->inWorkOrder())
            ->defaultGroup(
                Group::make('stage')
                    ->getTitleFromRecordUsing(fn (ProjectTask $record) => $record->stage?->code().' · '.$record->stage?->getLabel())
                    ->getDescriptionFromRecordUsing(fn (ProjectTask $record) => $record->stage?->getDescription())
                    ->titlePrefixedWithLabel(false)
                    ->orderQueryUsing(fn (Builder $query) => $query)
                    ->collapsible(),
            )
            ->groupingSettingsHidden()
            ->columns([
                TextColumn::make('title')
                    ->label('Work')
                    ->searchable()
                    ->weight('medium')
                    ->wrap()
                    ->description(fn (ProjectTask $record) => $record->description ? str($record->description)->limit(80)->toString() : null),
                ViewColumn::make('progress_percentage')
                    ->label('Progress')
                    ->view('filament.columns.progress-bar'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('updated_at')
                    ->label('Last update')
                    ->state(fn (ProjectTask $record) => $record->lastProgressUpdate()['by_name'] ?? null)
                    ->placeholder('—')
                    ->description(fn (ProjectTask $record) => $record->lastProgressUpdate()['at']?->diffForHumans()),
                TextColumn::make('due_date')
                    ->label('Target')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->color(fn (ProjectTask $record) => $record->isOverdue() ? 'danger' : null)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('stage')
                    ->options(ProjectStage::class),
                SelectFilter::make('status')
                    ->options([
                        TaskStatus::NotStarted->value => TaskStatus::NotStarted->getLabel(),
                        TaskStatus::InProgress->value => TaskStatus::InProgress->getLabel(),
                        TaskStatus::Completed->value => TaskStatus::Completed->getLabel(),
                    ]),
            ])
            ->headerActions([
                Action::make('addWork')
                    ->label('Add extra work')
                    ->icon('heroicon-o-plus')
                    ->color('gray')
                    ->visible(fn () => $this->canManageList())
                    ->modalWidth('2xl')
                    ->fillForm(fn () => ['stage' => $this->getOwnerRecord()->currentStage()])
                    ->schema(fn (Schema $schema) => $this->form($schema))
                    ->action(function (array $data) {
                        $this->getOwnerRecord()->tasks()->create([
                            ...$data,
                            'sort_order' => (int) $this->getOwnerRecord()->tasks()->where('stage', $data['stage'])->max('sort_order') + 10,
                            'discipline' => $this->getOwnerRecord()->discipline,
                        ]);

                        Notification::make()->title('Work added')->success()->send();
                    }),
                Action::make('applyTemplates')
                    ->label('Add missing standard works')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->visible(fn () => $this->canManageList())
                    ->requiresConfirmation()
                    ->modalDescription('Adds any standard works from Settings → Work Templates that this project does not have yet.')
                    ->action(function () {
                        $added = $this->getOwnerRecord()->applyWorkTemplates();

                        Notification::make()
                            ->title($added > 0 ? "{$added} work(s) added" : 'Nothing to add — all standard works are present')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                UpdateWorkProgressAction::make(),
                ActionGroup::make([
                    EditAction::make()
                        ->modalHeading('Edit work')
                        ->modalWidth('2xl')
                        ->authorize(fn () => $this->isManager()),
                    DeleteAction::make()
                        ->authorize(fn () => $this->isManager()),
                ])->visible(fn () => $this->canManageList()),
            ])
            ->emptyStateIcon('heroicon-o-squares-plus')
            ->emptyStateHeading('No works yet')
            ->emptyStateDescription('Standard works are added automatically when a project is created.')
            ->paginated(false);
    }
}
