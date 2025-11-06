<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\VoucherResource\RelationManagers;

use AIArmada\FilamentVouchers\Resources\VoucherUsageResource\Tables\VoucherUsagesTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

final class VoucherUsagesRelationManager extends RelationManager
{
    protected static string $relationship = 'usages';

    public function table(Table $table): Table
    {
        return VoucherUsagesTable::configure($table)
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
