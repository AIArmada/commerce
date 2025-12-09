<?php

declare(strict_types=1);

namespace AIArmada\FilamentStock\Resources;

use AIArmada\FilamentStock\Resources\StockReservationResource\Pages\ListStockReservations;
use AIArmada\FilamentStock\Resources\StockReservationResource\Pages\ViewStockReservation;
use AIArmada\FilamentStock\Resources\StockReservationResource\Schemas\StockReservationInfolist;
use AIArmada\FilamentStock\Resources\StockReservationResource\Tables\StockReservationsTable;
use AIArmada\Stock\Models\StockReservation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class StockReservationResource extends Resource
{
    protected static ?string $model = StockReservation::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $navigationLabel = 'Stock Reservations';

    protected static ?string $modelLabel = 'Stock Reservation';

    protected static ?string $pluralModelLabel = 'Stock Reservations';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StockReservationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockReservationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockReservations::route('/'),
            'view' => ViewStockReservation::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        /** @var class-string<StockReservation> $model */
        $model = self::getModel();
        $count = $model::query()->active()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        /** @var class-string<StockReservation> $model */
        $model = self::getModel();
        $count = $model::query()->active()->count();

        return $count > 0 ? 'warning' : 'success';
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-stock.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-stock.resources.navigation_sort.stock_reservations', 51);
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
