<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SignalGoalResource\Pages;

use AIArmada\FilamentSignals\Resources\SignalGoalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListSignalGoals extends ListRecords
{
    protected static string $resource = SignalGoalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
