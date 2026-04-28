<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SignalGoalResource\Pages;

use AIArmada\FilamentSignals\Resources\SignalGoalResource;
use AIArmada\FilamentSignals\Support\TrackedPropertyMutationGuard;
use Filament\Resources\Pages\EditRecord;

final class EditSignalGoal extends EditRecord
{
    protected static string $resource = SignalGoalResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return app(TrackedPropertyMutationGuard::class)->sanitize($data);
    }

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
