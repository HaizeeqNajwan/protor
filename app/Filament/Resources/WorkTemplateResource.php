<?php

namespace App\Filament\Resources;

use App\Enums\ProjectStage;
use App\Filament\Resources\WorkTemplateResource\Pages;
use App\Models\WorkTemplate;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** The standard works every new project starts with. Managers only. */
class WorkTemplateResource extends Resource
{
    protected static ?string $model = WorkTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Work Templates';

    protected static ?string $modelLabel = 'work template';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isManager();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
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
            Textarea::make('description')
                ->rows(2)
                ->columnSpanFull(),
            TextInput::make('sort_order')
                ->numeric()
                ->integer()
                ->default(fn () => (int) WorkTemplate::query()->max('sort_order') + 10)
                ->helperText('Lower numbers come first within a stage.'),
            Toggle::make('is_active')
                ->label('Add to new projects')
                ->default(true)
                ->inline(false),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Every new project is created with these works. Changes apply to projects created from now on.')
            ->modifyQueryUsing(fn (Builder $query) => $query->ordered())
            ->defaultGroup(
                Group::make('stage')
                    ->getTitleFromRecordUsing(fn (WorkTemplate $record) => $record->stage->code().' · '.$record->stage->getLabel())
                    ->titlePrefixedWithLabel(false)
                    ->orderQueryUsing(fn (Builder $query) => $query),
            )
            ->groupingSettingsHidden()
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('title')
                    ->label('Work')
                    ->weight('medium')
                    ->description(fn (WorkTemplate $record) => $record->description)
                    ->searchable(),
                ToggleColumn::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make()->modalWidth('2xl'),
                DeleteAction::make(),
            ])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageWorkTemplates::route('/'),
        ];
    }
}
