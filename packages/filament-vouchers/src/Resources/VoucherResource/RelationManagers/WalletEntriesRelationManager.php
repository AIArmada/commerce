<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\VoucherResource\RelationManagers;

use AIArmada\FilamentVouchers\Resources\VoucherResource\Tables\WalletEntriesTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

final class WalletEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'walletEntries';

    protected static ?string $title = 'Wallet Entries';

    protected static ?string $modelLabel = 'wallet entry';

    protected static ?string $pluralModelLabel = 'wallet entries';

    public function table(Table $table): Table
    {
        return WalletEntriesTable::configure($table);
    }
}
