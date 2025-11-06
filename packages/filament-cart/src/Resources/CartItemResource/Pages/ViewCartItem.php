<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\CartItemResource\Pages;

use AIArmada\FilamentCart\Resources\CartItemResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewCartItem extends ViewRecord
{
    protected static string $resource = CartItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No edit/delete actions - read-only resource
        ];
    }
}
