<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\CartConditionResource\Pages;

use AIArmada\FilamentCart\Resources\CartConditionResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewCartCondition extends ViewRecord
{
    protected static string $resource = CartConditionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No edit/delete actions - read-only resource
        ];
    }
}
