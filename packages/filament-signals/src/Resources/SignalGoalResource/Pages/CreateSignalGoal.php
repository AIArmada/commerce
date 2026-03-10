<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SignalGoalResource\Pages;

use AIArmada\FilamentSignals\Resources\SignalGoalResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateSignalGoal extends CreateRecord
{
    protected static string $resource = SignalGoalResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
