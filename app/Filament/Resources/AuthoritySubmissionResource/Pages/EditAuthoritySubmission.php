<?php

namespace App\Filament\Resources\AuthoritySubmissionResource\Pages;

use App\Filament\Resources\AuthoritySubmissionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAuthoritySubmission extends EditRecord
{
    protected static string $resource = AuthoritySubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
