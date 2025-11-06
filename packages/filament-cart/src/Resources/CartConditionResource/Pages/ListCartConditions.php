<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\CartConditionResource\Pages;

use AIArmada\FilamentCart\Resources\CartConditionResource;
use Filament\Resources\Pages\ListRecords;

final class ListCartConditions extends ListRecords
{
    protected static string $resource = CartConditionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - read-only resource
        ];
    }
}
