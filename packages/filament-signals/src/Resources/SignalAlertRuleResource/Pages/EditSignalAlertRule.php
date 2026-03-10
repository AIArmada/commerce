<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SignalAlertRuleResource\Pages;

use AIArmada\FilamentSignals\Resources\SignalAlertRuleResource;
use Filament\Resources\Pages\EditRecord;

final class EditSignalAlertRule extends EditRecord
{
    protected static string $resource = SignalAlertRuleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
