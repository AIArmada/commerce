<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\CartResource\Pages;

use AIArmada\FilamentCart\Resources\CartResource;
use Filament\Resources\Pages\ListRecords;

final class ListCarts extends ListRecords
{
    protected static string $resource = CartResource::class;

    public function getTitle(): string
    {
        return 'Shopping Carts';
    }

    public function getSubheading(): string
    {
        return 'Manage customer shopping carts and cart sessions';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // Can add cart statistics widgets here
        ];
    }
}
