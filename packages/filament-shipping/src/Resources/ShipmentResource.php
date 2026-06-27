<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\FilamentShipping\Resources\ShipmentResource\Pages;
use AIArmada\FilamentShipping\Resources\ShipmentResource\RelationManagers;
use AIArmada\FilamentShipping\Resources\ShipmentResource\Schemas\ShipmentForm;
use AIArmada\FilamentShipping\Resources\ShipmentResource\Tables\ShipmentsTable;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\ShippingManager;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ShipmentResource extends Resource
{
    protected static ?string $model = Shipment::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedTruck;

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-shipping.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-shipping.resources.navigation_sort.shipments');
    }

    /**
     * @return Builder<Shipment>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Shipment> $query */
        $query = parent::getEloquentQuery()->withoutGlobalScope(OwnerScope::class);

        if (! (bool) config('shipping.features.owner.enabled', false)) {
            return $query;
        }

        $owner = OwnerContext::resolve();
        if ($owner === null) {
            return $query->whereRaw('0 = 1');
        }

        /** @var Builder<Shipment> $scoped */
        $scoped = $query->forOwner($owner, includeGlobal: (bool) config('shipping.features.owner.include_global', false));

        return $scoped;
    }

    public static function form(Schema $schema): Schema
    {
        return ShipmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShipmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
            RelationManagers\EventsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShipments::route('/'),
            'create' => Pages\CreateShipment::route('/create'),
            'view' => Pages\ViewShipment::route('/{record}'),
            'edit' => Pages\EditShipment::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getCarrierOptions(): array
    {
        $shipping = app(ShippingManager::class);

        return collect($shipping->getAvailableDrivers())
            ->mapWithKeys(fn ($driver) => [$driver => ucfirst($driver)])
            ->toArray();
    }
}
