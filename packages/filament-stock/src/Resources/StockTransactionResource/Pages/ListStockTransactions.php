<?php

declare(strict_types=1);

namespace AIArmada\FilamentStock\Resources\StockTransactionResource\Pages;

use AIArmada\FilamentStock\Actions\QuickAddStockAction;
use AIArmada\FilamentStock\Resources\StockTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListStockTransactions extends ListRecords
{
    protected static string $resource = StockTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            QuickAddStockAction::make(),
            Actions\CreateAction::make(),
        ];
    }
}
