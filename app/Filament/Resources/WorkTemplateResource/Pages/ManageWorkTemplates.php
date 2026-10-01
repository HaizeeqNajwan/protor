<?php

namespace App\Filament\Resources\WorkTemplateResource\Pages;

use App\Filament\Resources\WorkTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageWorkTemplates extends ManageRecords
{
    protected static string $resource = WorkTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New work template')
                ->modalWidth('2xl'),
        ];
    }
}
