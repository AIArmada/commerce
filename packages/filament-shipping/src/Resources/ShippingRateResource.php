<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentShipping\Resources\ShippingRateResource\Pages;
use AIArmada\FilamentShipping\Resources\ShippingRateResource\Schemas\ShippingRateForm;
use AIArmada\FilamentShipping\Resources\ShippingRateResource\Tables\ShippingRatesTable;
use AIArmada\Shipping\Models\ShippingRate;
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

    protected static string | UnitEnum | null $navigationGroup = 'Shipping';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Shipping Rates';

    /**
     * @return Builder<ShippingRate>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<ShippingRate> $query */
        $query = parent::getEloquentQuery()->with('zone');

        if (! (bool) config('shipping.features.owner.enabled', false)) {
            return $query;
        }

        $owner = OwnerContext::resolve();
        if ($owner === null) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereHas('zone', /** @phpstan-ignore-next-line */ function (Builder $q) use ($owner): void {
            $q->forOwner($owner, includeGlobal: (bool) config('shipping.features.owner.include_global', false)); // @phpstan-ignore method.notFound
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
