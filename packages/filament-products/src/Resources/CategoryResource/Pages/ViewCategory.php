<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\CategoryResource\Pages;

use AIArmada\FilamentProducts\Resources\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

final class ViewCategory extends ViewRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
