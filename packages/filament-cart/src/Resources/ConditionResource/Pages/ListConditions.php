<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\ConditionResource\Pages;

use AIArmada\FilamentCart\Resources\ConditionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

final class ListConditions extends ListRecords
{
    protected static string $resource = ConditionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon(Heroicon::OutlinedPlus),
        ];
    }
}
