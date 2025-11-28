<?php

declare(strict_types=1);

namespace AIArmada\FilamentStock\Resources\StockReservationResource\Pages;

use AIArmada\FilamentStock\Actions\ExtendReservationAction;
use AIArmada\FilamentStock\Actions\ReleaseReservationAction;
use AIArmada\FilamentStock\Resources\StockReservationResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewStockReservation extends ViewRecord
{
    protected static string $resource = StockReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExtendReservationAction::make(),
            ReleaseReservationAction::make(),
        ];
    }
}
