<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SignalInteractionRuleResource\Pages;

use AIArmada\FilamentSignals\Resources\SignalInteractionRuleResource;
use AIArmada\FilamentSignals\Support\TrackedPropertyMutationGuard;
use Filament\Resources\Pages\EditRecord;

final class EditSignalInteractionRule extends EditRecord
{
    protected static string $resource = SignalInteractionRuleResource::class;

    public function getTitle(): string
    {
        return 'Edit interaction rule';
    }

    public function getSubheading(): ?string
    {
        return 'Update selector, page scope, or event mapping for this tracked browser interaction.';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return app(TrackedPropertyMutationGuard::class)->sanitize($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
