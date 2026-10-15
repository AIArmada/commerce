<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressCityResource\Pages;

use AIArmada\FilamentAddressing\Resources\AddressCityResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewAddressCity extends ViewRecord
{
    protected static string $resource = AddressCityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (): bool => ! AddressCityResource::isReadOnly() && AddressCityResource::canEdit($this->getRecord())),
        ];
    }
}
