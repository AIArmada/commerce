<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources;

use AIArmada\Addressing\Models\City;
use AIArmada\FilamentAddressing\Resources\AddressCityResource\Pages\EditAddressCity;
use AIArmada\FilamentAddressing\Resources\AddressCityResource\Pages\ListAddressCities;
use AIArmada\FilamentAddressing\Resources\AddressCityResource\Pages\ViewAddressCity;
use AIArmada\FilamentAddressing\Tables\AddressCityTable;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

final class AddressCityResource extends Resource
{
    protected static ?string $slug = 'cities';

    protected static ?string $model = City::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-building-office';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-addressing.navigation.group');
    }

    public static function getNavigationIcon(): BackedEnum | string | null
    {
        return config('filament-addressing.navigation.icons.cities', parent::getNavigationIcon());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-addressing.navigation.enabled', true);
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-addressing.navigation.sort', 80);
    }

    public static function getModel(): string
    {
        return config('filament-addressing.resources.cities.model', City::class);
    }

    public static function table(Table $table): Table
    {
        return AddressCityTable::make($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Identity')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('latitude')
                            ->numeric(5),
                        TextEntry::make('longitude')
                            ->numeric(5),
                    ])->columns(2),
                Section::make('Location')
                    ->schema([
                        TextEntry::make('country.name'),
                        TextEntry::make('country.iso2')->label('ISO2'),
                        TextEntry::make('state.name')->label('State'),
                        TextEntry::make('state.code')->label('State Code'),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        $pages = [
            'index' => ListAddressCities::route('/'),
            'view' => ViewAddressCity::route('/{record}'),
        ];

        if (! self::isReadOnly()) {
            $pages['edit'] = EditAddressCity::route('/{record}/edit');
        }

        return $pages;
    }

    public static function isReadOnly(): bool
    {
        return (bool) config('filament-addressing.resources.cities.read_only', false);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Identity')
                    ->schema([
                        TextInput::make('name')->required(),
                        TextInput::make('latitude')
                            ->numeric()
                            ->step(0.0000001),
                        TextInput::make('longitude')
                            ->numeric()
                            ->step(0.0000001),
                    ])->columns(2),
                Section::make('Location')
                    ->schema([
                        Select::make('country_id')
                            ->relationship('country', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($set) => $set('state_id', null)),
                        Select::make('state_id')
                            ->label('State')
                            ->relationship('state', 'name', fn ($query, $get) => $query->where('country_id', $get('country_id')))
                            ->searchable()
                            ->preload(),
                    ])->columns(2),
            ]);
    }
}
