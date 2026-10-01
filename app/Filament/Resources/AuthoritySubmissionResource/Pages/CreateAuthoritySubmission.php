<?php

namespace App\Filament\Resources\AuthoritySubmissionResource\Pages;

use App\Filament\Resources\AuthoritySubmissionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAuthoritySubmission extends CreateRecord
{
    protected static string $resource = AuthoritySubmissionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['submitted_by'] = auth()->id();
        $data['query_logs'] = [];

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
