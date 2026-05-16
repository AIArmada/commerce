<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Resources\VariantResource\Pages;

use AIArmada\FilamentGrowth\Resources\VariantResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateVariant extends CreateRecord
{
    protected static string $resource = VariantResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}