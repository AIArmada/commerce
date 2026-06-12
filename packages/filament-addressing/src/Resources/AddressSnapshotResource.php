<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources;

use AIArmada\Addressing\Models\AddressSnapshot;
use AIArmada\FilamentAddressing\Resources\AddressSnapshotResource\Pages\ListAddressSnapshots;
use AIArmada\FilamentAddressing\Resources\AddressSnapshotResource\Pages\ViewAddressSnapshot;
use AIArmada\FilamentAddressing\Tables\AddressSnapshotTable;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

final class AddressSnapshotResource extends Resource
{
    protected static ?string $model = AddressSnapshot::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 83;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-addressing.navigation.group');
    }

    public static function getModel(): string
    {
        return config('filament-addressing.resources.snapshots.model', AddressSnapshot::class);
    }

    public static function table(Table $table): Table
    {
        return AddressSnapshotTable::make($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Snapshot')
                    ->schema([
                        TextEntry::make('snapshotable_type')->label('Source Type'),
                        TextEntry::make('snapshotable_id')->label('Source ID'),
                        TextEntry::make('reason')->badge(),
                        TextEntry::make('formatted_address'),
                    ])->columns(2),
                Section::make('Address Details')
                    ->schema([
                        TextEntry::make('label'),
                        TextEntry::make('line1'),
                        TextEntry::make('line2'),
                        TextEntry::make('city'),
                        TextEntry::make('district'),
                        TextEntry::make('state'),
                        TextEntry::make('postcode'),
                        TextEntry::make('country'),
                        TextEntry::make('country_code')->label('Country Code'),
                    ])->columns(3),
                Section::make('Coordinates')
                    ->schema([
                        TextEntry::make('latitude'),
                        TextEntry::make('longitude'),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAddressSnapshots::route('/'),
            'view' => ViewAddressSnapshot::route('/{record}'),
        ];
    }
}
