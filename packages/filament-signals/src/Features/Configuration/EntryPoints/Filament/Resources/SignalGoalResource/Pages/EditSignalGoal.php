<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\SignalGoalResource\Pages;

use AIArmada\Signals\Features\Reporting\Api\GoalAdministration;
use Illuminate\Database\Eloquent\Model;
use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\SignalGoalResource;
use AIArmada\FilamentSignals\Support\TrackedPropertyMutationGuard;
use Filament\Resources\Pages\EditRecord;

final class EditSignalGoal extends EditRecord
{
    protected static string $resource = SignalGoalResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(GoalAdministration::class)->update((string) $record->getKey(), $data);

        return $record->refresh();
    }

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
