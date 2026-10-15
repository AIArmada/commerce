<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressAreaResource\Pages;

use AIArmada\Addressing\Models\AddressArea;
use AIArmada\FilamentAddressing\RelationManagers\ChildAreasRelationManager;
use AIArmada\FilamentAddressing\RelationManagers\IncomingAreaRelationshipsRelationManager;
use AIArmada\FilamentAddressing\RelationManagers\OutgoingAreaRelationshipsRelationManager;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewAddressArea extends ViewRecord
{
    protected static string $resource = AddressAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (AddressArea $record): bool => ! AddressAreaResource::isReadOnly()
                    && AddressAreaResource::canEdit($record))
                ->authorize(fn (AddressArea $record): bool => ! AddressAreaResource::isReadOnly()
                    && AddressAreaResource::canEdit($record)),
        ];
    }

    public function getRelationManagers(): array
    {
        return [
            ChildAreasRelationManager::class,
            ...parent::getRelationManagers(),
            OutgoingAreaRelationshipsRelationManager::class,
            IncomingAreaRelationshipsRelationManager::class,
        ];
    }
}
