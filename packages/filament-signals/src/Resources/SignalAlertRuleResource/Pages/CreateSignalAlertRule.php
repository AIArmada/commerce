<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SignalAlertRuleResource\Pages;

use AIArmada\FilamentSignals\Resources\SignalAlertRuleResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateSignalAlertRule extends CreateRecord
{
    protected static string $resource = SignalAlertRuleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
