<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources;

use AIArmada\Addressing\Models\Address;
use AIArmada\FilamentAddressing\Resources\AddressResource\Pages\EditAddress;
use AIArmada\FilamentAddressing\Resources\AddressResource\Pages\ListAddresses;
use AIArmada\FilamentAddressing\Resources\AddressResource\Pages\ViewAddress;
use AIArmada\FilamentAddressing\Schemas\AddressFormSchema;
use AIArmada\FilamentAddressing\Schemas\AddressInfolistSchema;
use AIArmada\FilamentAddressing\Tables\AddressTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

final class AddressResource extends Resource
{
    protected static ?string $model = Address::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-map-pin';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-addressing.navigation.group');
    }

    public static function getNavigationIcon(): BackedEnum | string | null
    {
        return config('filament-addressing.navigation.icons.addresses', parent::getNavigationIcon());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-addressing.navigation.enabled', true);
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-addressing.navigation.sort', 80) + 2;
    }

    public static function getModel(): string
    {
        return config('filament-addressing.resources.addresses.model', Address::class);
    }

    public static function table(Table $table): Table
    {
        return AddressTable::make($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AddressInfolistSchema::make($schema);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema(AddressFormSchema::make());
    }

    public static function getPages(): array
    {
        $pages = [
            'index' => ListAddresses::route('/'),
            'view' => ViewAddress::route('/{record}'),
        ];

        if (! self::isReadOnly()) {
            $pages['edit'] = EditAddress::route('/{record}/edit');
        }

        return $pages;
    }

    public static function isReadOnly(): bool
    {
        return (bool) config('filament-addressing.resources.addresses.read_only', false);
    }
}
