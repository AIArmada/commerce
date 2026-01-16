<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\AttributeSetResource\Pages;

use AIArmada\FilamentProducts\Resources\AttributeSetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListAttributeSets extends ListRecords
{
    protected static string $resource = AttributeSetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
