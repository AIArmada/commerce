<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SignalGoalResource\Pages;

use AIArmada\FilamentSignals\Resources\SignalGoalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListSignalGoals extends ListRecords
{
    protected static string $resource = SignalGoalResource::class;

    public function getTitle(): string
    {
        return 'Goals';
    }

    public function getSubheading(): ?string
    {
        return 'Track the events that count as success for dashboards, funnels, and alerts.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New goal'),
        ];
    }
}
