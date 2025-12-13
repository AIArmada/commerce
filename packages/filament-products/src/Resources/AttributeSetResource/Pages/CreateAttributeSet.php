<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\AttributeSetResource\Pages;

use AIArmada\FilamentProducts\Resources\AttributeSetResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttributeSet extends CreateRecord
{
    protected static string $resource = AttributeSetResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
