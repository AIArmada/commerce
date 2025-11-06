<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\CartItemResource\Pages;

use AIArmada\FilamentCart\Resources\CartItemResource;
use Filament\Resources\Pages\ListRecords;

final class ListCartItems extends ListRecords
{
    protected static string $resource = CartItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - read-only resource
        ];
    }
}
