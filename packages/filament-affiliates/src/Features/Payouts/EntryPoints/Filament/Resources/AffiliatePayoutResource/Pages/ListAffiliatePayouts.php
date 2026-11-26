<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Resources\AffiliatePayoutResource\Pages;

use AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Resources\AffiliatePayoutResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListAffiliatePayouts extends ListRecords
{
    protected static string $resource = AffiliatePayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
