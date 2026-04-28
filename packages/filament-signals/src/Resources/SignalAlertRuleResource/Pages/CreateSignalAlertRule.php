<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SignalAlertRuleResource\Pages;

use AIArmada\FilamentSignals\Resources\SignalAlertRuleResource;
use AIArmada\FilamentSignals\Support\TrackedPropertyMutationGuard;
use Filament\Resources\Pages\CreateRecord;

final class CreateSignalAlertRule extends CreateRecord
{
    protected static string $resource = SignalAlertRuleResource::class;

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
