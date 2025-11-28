<?php

declare(strict_types=1);

namespace AIArmada\FilamentStock\Resources\StockReservationResource\Pages;

use AIArmada\FilamentStock\Resources\StockReservationResource;
use Filament\Resources\Pages\ListRecords;

final class ListStockReservations extends ListRecords
{
    protected static string $resource = StockReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
