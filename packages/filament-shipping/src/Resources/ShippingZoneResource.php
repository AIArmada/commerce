<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources;

use AIArmada\FilamentShipping\Resources\ShippingZoneResource\Pages;
use AIArmada\FilamentShipping\Resources\ShippingZoneResource\RelationManagers;
use AIArmada\FilamentShipping\Resources\ShippingZoneResource\Schemas\ShippingZoneForm;
use AIArmada\FilamentShipping\Resources\ShippingZoneResource\Tables\ShippingZonesTable;
use AIArmada\FilamentShipping\Support\OwnerScopedQuery;
use AIArmada\Shipping\Features\Rates\Models\ShippingZone;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ShippingZoneResource extends Resource
{
    protected static ?string $model = ShippingZone::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedMap;

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-shipping.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-shipping.resources.navigation_sort.zones');
    }

    /**
     * @return Builder<ShippingZone>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<ShippingZone> $query */
        $query = parent::getEloquentQuery()->withCount('rates');

        return OwnerScopedQuery::apply($query);
    }

    public static function form(Schema $schema): Schema
    {
        return ShippingZoneForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShippingZonesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShippingZones::route('/'),
            'create' => Pages\CreateShippingZone::route('/create'),
            'edit' => Pages\EditShippingZone::route('/{record}/edit'),
        ];
    }
}
