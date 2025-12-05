<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\FraudSignalResource\Pages;

use AIArmada\FilamentVouchers\Actions\MarkFraudReviewedAction;
use AIArmada\FilamentVouchers\Resources\FraudSignalResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewFraudSignal extends ViewRecord
{
    protected static string $resource = FraudSignalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MarkFraudReviewedAction::make(),
        ];
    }
}
