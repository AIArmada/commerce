<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\SignalInteractionRuleResource\Pages;

use AIArmada\Signals\Features\Ingestion\Api\InteractionRuleAdministration;
use AIArmada\Signals\Features\Ingestion\Models\SignalInteractionRule;
use Illuminate\Database\Eloquent\Model;
use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\SignalInteractionRuleResource;
use AIArmada\FilamentSignals\Support\TrackedPropertyMutationGuard;
use Filament\Resources\Pages\CreateRecord;

final class CreateSignalInteractionRule extends CreateRecord
{
    protected static string $resource = SignalInteractionRuleResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $id = app(InteractionRuleAdministration::class)->create($data);

        return SignalInteractionRule::query()->findOrFail($id);
    }

    public function getTitle(): string
    {
        return 'Create interaction rule';
    }

    public function getSubheading(): ?string
    {
        return 'Map a page interaction to a Signals event and toggle it on without touching frontend code.';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return app(TrackedPropertyMutationGuard::class)->sanitize($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
