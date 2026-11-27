<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Alerts\EntryPoints\Filament\Resources\SignalAlertRuleResource\Pages;

use AIArmada\FilamentSignals\Features\Alerts\EntryPoints\Filament\Resources\SignalAlertRuleResource;
use AIArmada\FilamentSignals\Platform\TrackedPropertyMutationGuard;
use AIArmada\Signals\Features\Alerts\Api\AlertRuleAdministration;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditSignalAlertRule extends EditRecord
{
    protected static string $resource = SignalAlertRuleResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ((bool) config('signals.features.alerts.allow_inline_destinations', false)) {
            $data['inline_destinations'] = $this->getRecord()->getAttribute('inline_destinations');
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(AlertRuleAdministration::class)->update((string) $record->getKey(), $data);

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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
