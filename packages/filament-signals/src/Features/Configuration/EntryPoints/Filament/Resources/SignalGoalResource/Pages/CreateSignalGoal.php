<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\SignalGoalResource\Pages;

use AIArmada\Signals\Features\Reporting\Api\GoalAdministration;
use AIArmada\Signals\Features\Reporting\Models\SignalGoal;
use Illuminate\Database\Eloquent\Model;
use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\SignalGoalResource;
use AIArmada\FilamentSignals\Support\TrackedPropertyMutationGuard;
use Filament\Resources\Pages\CreateRecord;

final class CreateSignalGoal extends CreateRecord
{
    protected static string $resource = SignalGoalResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $id = app(GoalAdministration::class)->create($data);

        return SignalGoal::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return app(TrackedPropertyMutationGuard::class)->sanitize($data);
    }

    public function getTitle(): string
    {
        return 'Create goal';
    }

    public function getSubheading(): ?string
    {
        return 'Choose the success event you want to track, then narrow it with optional rules.';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
