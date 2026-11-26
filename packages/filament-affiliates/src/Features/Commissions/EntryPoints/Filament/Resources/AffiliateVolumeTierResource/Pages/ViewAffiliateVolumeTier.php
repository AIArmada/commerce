<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Resources\AffiliateVolumeTierResource\Pages;

use AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Resources\AffiliateVolumeTierResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

final class ViewAffiliateVolumeTier extends ViewRecord
{
    protected static string $resource = AffiliateVolumeTierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
