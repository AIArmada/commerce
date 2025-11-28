<?php

declare(strict_types=1);

namespace AIArmada\FilamentStock\Resources;

use AIArmada\FilamentStock\Resources\StockTransactionResource\Pages\CreateStockTransaction;
use AIArmada\FilamentStock\Resources\StockTransactionResource\Pages\EditStockTransaction;
use AIArmada\FilamentStock\Resources\StockTransactionResource\Pages\ListStockTransactions;
use AIArmada\FilamentStock\Resources\StockTransactionResource\Pages\ViewStockTransaction;
use AIArmada\FilamentStock\Resources\StockTransactionResource\Schemas\StockTransactionForm;
use AIArmada\FilamentStock\Resources\StockTransactionResource\Schemas\StockTransactionInfolist;
use AIArmada\FilamentStock\Resources\StockTransactionResource\Tables\StockTransactionsTable;
use AIArmada\Stock\Models\StockTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class StockTransactionResource extends Resource
{
    protected static ?string $model = StockTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $navigationLabel = 'Stock Transactions';

    protected static ?string $modelLabel = 'Stock Transaction';

    protected static ?string $pluralModelLabel = 'Stock Transactions';

    public static function form(Schema $schema): Schema
    {
        return StockTransactionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StockTransactionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockTransactionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockTransactions::route('/'),
            'create' => CreateStockTransaction::route('/create'),
            'view' => ViewStockTransaction::route('/{record}'),
            'edit' => EditStockTransaction::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getModel()::count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'primary';
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return config('filament-stock.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-stock.resources.navigation_sort.stock_transactions', 50);
    }
}
