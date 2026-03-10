<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SignalAlertRuleResource\Pages;

use AIArmada\FilamentSignals\Resources\SignalAlertRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListSignalAlertRules extends ListRecords
{
    protected static string $resource = SignalAlertRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
