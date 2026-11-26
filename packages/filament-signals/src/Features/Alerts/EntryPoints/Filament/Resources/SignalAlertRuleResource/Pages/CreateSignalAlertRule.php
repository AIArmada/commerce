<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Alerts\EntryPoints\Filament\Resources\SignalAlertRuleResource\Pages;

use AIArmada\FilamentSignals\Features\Alerts\EntryPoints\Filament\Resources\SignalAlertRuleResource;
use AIArmada\FilamentSignals\Platform\TrackedPropertyMutationGuard;
use AIArmada\Signals\Features\Alerts\Api\AlertRuleAdministration;
use AIArmada\Signals\Features\Alerts\Models\SignalAlertRule;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateSignalAlertRule extends CreateRecord
{
    protected static string $resource = SignalAlertRuleResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $id = app(AlertRuleAdministration::class)->create($data);

        return SignalAlertRule::query()->findOrFail($id);
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
