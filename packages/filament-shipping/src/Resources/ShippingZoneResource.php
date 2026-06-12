<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\FilamentShipping\Resources\ShippingZoneResource\Pages;
use AIArmada\FilamentShipping\Resources\ShippingZoneResource\RelationManagers;
use AIArmada\FilamentShipping\Resources\ShippingZoneResource\Schemas\ShippingZoneForm;
use AIArmada\FilamentShipping\Resources\ShippingZoneResource\Tables\ShippingZonesTable;
use AIArmada\Shipping\Models\ShippingZone;
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

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-shipping.navigation.group');
    }

    /**
     * @return Builder<ShippingZone>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<ShippingZone> $query */
        $query = parent::getEloquentQuery()->withoutGlobalScope(OwnerScope::class)->withCount('rates');

        if (! (bool) config('shipping.features.owner.enabled', false)) {
            return $query;
        }

        $owner = OwnerContext::resolve();
        if ($owner === null) {
            return $query->whereRaw('0 = 1');
        }

        /** @var Builder<ShippingZone> $scoped */
        $scoped = $query->forOwner($owner, includeGlobal: (bool) config('shipping.features.owner.include_global', false));

        return $scoped;
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
