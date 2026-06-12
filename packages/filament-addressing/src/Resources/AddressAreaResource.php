<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources;

use AIArmada\Addressing\Models\AddressArea;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource\Pages\CreateAddressArea;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource\Pages\EditAddressArea;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource\Pages\ListAddressAreas;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource\Pages\ViewAddressArea;
use AIArmada\FilamentAddressing\Schemas\AddressAreaFormSchema;
use AIArmada\FilamentAddressing\Tables\AddressAreaTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

final class AddressAreaResource extends Resource
{
    protected static ?string $model = AddressArea::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-map';

    protected static ?int $navigationSort = 81;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-addressing.navigation.group');
    }

    public static function getModel(): string
    {
        return config('filament-addressing.resources.areas.model', AddressArea::class);
    }

    public static function table(Table $table): Table
    {
        return AddressAreaTable::make($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AddressAreaFormSchema::infolist($schema);
    }

    public static function form(Schema $schema): Schema
    {
        return AddressAreaFormSchema::form($schema);
    }

    public static function getPages(): array
    {
        $pages = [
            'index' => ListAddressAreas::route('/'),
            'view' => ViewAddressArea::route('/{record}'),
            'edit' => EditAddressArea::route('/{record}/edit'),
        ];

        if (! self::isReadOnly()) {
            $pages['create'] = CreateAddressArea::route('/create');
        }

        return $pages;
    }

    public static function isReadOnly(): bool
    {
        return (bool) config('filament-addressing.resources.areas.read_only', false);
    }
}
