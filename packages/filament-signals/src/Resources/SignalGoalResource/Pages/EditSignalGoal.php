<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SignalGoalResource\Pages;

use AIArmada\FilamentSignals\Resources\SignalGoalResource;
use Filament\Resources\Pages\EditRecord;

final class EditSignalGoal extends EditRecord
{
    protected static string $resource = SignalGoalResource::class;

    public function getTitle(): string
    {
        return 'Edit goal';
    }

    public function getSubheading(): ?string
    {
        return 'Update what this goal counts and any optional rules around it.';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
