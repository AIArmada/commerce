<?php

declare(strict_types=1);

namespace AIArmada\FilamentStock\Resources\StockTransactionResource\Pages;

use AIArmada\FilamentStock\Resources\StockTransactionResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateStockTransaction extends CreateRecord
{
    protected static string $resource = StockTransactionResource::class;
}
