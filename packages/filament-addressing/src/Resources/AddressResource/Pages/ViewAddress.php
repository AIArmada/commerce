<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressResource\Pages;

use AIArmada\FilamentAddressing\Resources\AddressResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewAddress extends ViewRecord
{
    protected static string $resource = AddressResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (): bool => ! AddressResource::isReadOnly() && AddressResource::canEdit($this->getRecord())),
        ];
    }
}
