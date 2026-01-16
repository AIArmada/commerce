<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\CollectionResource\Pages;

use AIArmada\FilamentProducts\Resources\CollectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListCollections extends ListRecords
{
    protected static string $resource = CollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
