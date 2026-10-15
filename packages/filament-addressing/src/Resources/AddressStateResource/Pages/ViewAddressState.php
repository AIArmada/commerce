<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressStateResource\Pages;

use AIArmada\FilamentAddressing\Resources\AddressStateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewAddressState extends ViewRecord
{
    protected static string $resource = AddressStateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (): bool => ! AddressStateResource::isReadOnly() && AddressStateResource::canEdit($this->getRecord())),
        ];
    }
}
