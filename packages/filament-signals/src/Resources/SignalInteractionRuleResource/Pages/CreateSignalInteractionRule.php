<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SignalInteractionRuleResource\Pages;

use AIArmada\FilamentSignals\Resources\SignalInteractionRuleResource;
use AIArmada\FilamentSignals\Support\TrackedPropertyMutationGuard;
use Filament\Resources\Pages\CreateRecord;

final class CreateSignalInteractionRule extends CreateRecord
{
    protected static string $resource = SignalInteractionRuleResource::class;

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
