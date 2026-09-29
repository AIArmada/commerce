<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\VoucherResource\RelationManagers;

use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\FilamentVouchers\Resources\VoucherUsageResource\Tables\VoucherUsagesTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

final class VoucherUsagesRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'usages';

    public function table(Table $table): Table
    {
        return VoucherUsagesTable::configure($table)
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
