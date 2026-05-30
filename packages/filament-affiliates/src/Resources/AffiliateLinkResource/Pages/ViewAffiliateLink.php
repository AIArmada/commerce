<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateLinkResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateLinkResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

final class ViewAffiliateLink extends ViewRecord
{
    protected static string $resource = AffiliateLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
