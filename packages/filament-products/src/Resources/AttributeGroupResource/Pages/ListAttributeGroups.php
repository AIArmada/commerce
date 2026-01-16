<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\AttributeGroupResource\Pages;

use AIArmada\FilamentProducts\Resources\AttributeGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListAttributeGroups extends ListRecords
{
    protected static string $resource = AttributeGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
