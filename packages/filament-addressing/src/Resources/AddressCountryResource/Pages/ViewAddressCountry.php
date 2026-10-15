<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressCountryResource\Pages;

use AIArmada\FilamentAddressing\Resources\AddressCountryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewAddressCountry extends ViewRecord
{
    protected static string $resource = AddressCountryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (): bool => ! AddressCountryResource::isReadOnly() && AddressCountryResource::canEdit($this->getRecord())),
        ];
    }
}
