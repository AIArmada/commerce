<?php

declare(strict_types=1);

namespace AIArmada\FilamentStock\Resources\StockTransactionResource\Pages;

use AIArmada\FilamentStock\Resources\StockTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditStockTransaction extends EditRecord
{
    protected static string $resource = StockTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
