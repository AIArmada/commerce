<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\AttributeSetResource\Pages;

use AIArmada\FilamentProducts\Resources\AttributeSetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAttributeSet extends EditRecord
{
    protected static string $resource = AttributeSetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
