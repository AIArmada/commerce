<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\AttributeGroupResource\Pages;

use AIArmada\FilamentProducts\Resources\AttributeGroupResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttributeGroup extends CreateRecord
{
    protected static string $resource = AttributeGroupResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
