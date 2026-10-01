<?php

namespace App\Filament\Resources\ProjectResource\Pages\Concerns;

use Filament\Resources\Pages\Enums\ContentTabPosition;

/** Project pages open on the "Works" tab, with the project form in a "Details" tab next to it. */
trait ShowsWorksTabFirst
{
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Project details';
    }

    public function getContentTabIcon(): string
    {
        return 'heroicon-o-document-text';
    }

    public function getContentTabPosition(): ContentTabPosition
    {
        return ContentTabPosition::After;
    }

    /** Works is the first (and only) relation manager, so its tab key is "0". */
    public function mountShowsWorksTabFirst(): void
    {
        $this->activeRelationManager ??= '0';
    }
}
