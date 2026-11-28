<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources;

use AIArmada\FilamentShipping\Resources\ShippingRateResource\Pages;
use AIArmada\FilamentShipping\Resources\ShippingRateResource\Schemas\ShippingRateForm;
use AIArmada\FilamentShipping\Resources\ShippingRateResource\Tables\ShippingRatesTable;
use AIArmada\FilamentShipping\Support\OwnerScopedQuery;
use AIArmada\Shipping\Features\Rates\Models\ShippingRate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ShippingRateResource extends Resource
{
    protected static ?string $model = ShippingRate::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-shipping.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-shipping.resources.navigation_sort.rates');
    }

    protected static ?string $navigationLabel = 'Shipping Rates';

    /**
     * @return Builder<ShippingRate>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<ShippingRate> $query */
        $query = parent::getEloquentQuery()->with('zone');

        return $query->whereHas('zone', function (Builder $zoneQuery): void {
            OwnerScopedQuery::apply($zoneQuery);
        });
    }

    public static function form(Schema $schema): Schema
    {
        return ShippingRateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShippingRatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShippingRates::route('/'),
            'create' => Pages\CreateShippingRate::route('/create'),
            'edit' => Pages\EditShippingRate::route('/{record}/edit'),
        ];
    }
}
