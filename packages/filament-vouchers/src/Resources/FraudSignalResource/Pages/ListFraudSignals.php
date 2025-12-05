<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\FraudSignalResource\Pages;

use AIArmada\FilamentVouchers\Resources\FraudSignalResource;
use AIArmada\FilamentVouchers\Widgets\FraudStatsWidget;
use Filament\Resources\Pages\ListRecords;

final class ListFraudSignals extends ListRecords
{
    protected static string $resource = FraudSignalResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            FraudStatsWidget::class,
        ];
    }
}
