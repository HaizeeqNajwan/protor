<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\ProjectResource\Pages\Concerns\ShowsWorksTabFirst;
use App\Filament\Resources\ProjectResource\Widgets\ProjectStagesWidget;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProject extends ViewRecord
{
    use ShowsWorksTabFirst;

    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ProjectResource::completeProjectAction(),
            ProjectResource::reopenProjectAction(),
            ProjectResource::switchStageAction(),
            EditAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ProjectStagesWidget::class,
        ];
    }
}
