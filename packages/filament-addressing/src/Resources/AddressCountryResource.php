<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources;

use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\FilamentAddressing\Resources\AddressCountryResource\Pages\EditAddressCountry;
use AIArmada\FilamentAddressing\Resources\AddressCountryResource\Pages\ListAddressCountries;
use AIArmada\FilamentAddressing\Resources\AddressCountryResource\Pages\ViewAddressCountry;
use AIArmada\FilamentAddressing\Tables\AddressCountryTable;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

final class AddressCountryResource extends Resource
{
    protected static ?string $model = AddressCountry::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?int $navigationSort = 80;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-addressing.navigation.group');
    }

    public static function getModel(): string
    {
        return config('filament-addressing.resources.countries.model', AddressCountry::class);
    }

    public static function table(Table $table): Table
    {
        return AddressCountryTable::make($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Identity')
                    ->schema([
                        TextEntry::make('iso2')->label('ISO2'),
                        TextEntry::make('iso3')->label('ISO3'),
                        TextEntry::make('numeric_code'),
                        TextEntry::make('name'),
                        TextEntry::make('official_name'),
                        TextEntry::make('common_name'),
                        TextEntry::make('native_name'),
                        TextEntry::make('emoji'),
                    ])->columns(3),
                Section::make('Classification')
                    ->schema([
                        TextEntry::make('entity_type')->badge(),
                        TextEntry::make('is_independent')
                            ->label('Independent')
                            ->badge()
                            ->state(fn (AddressCountry $record): string => $record->is_independent ? 'Yes' : 'No'),
                        TextEntry::make('region'),
                        TextEntry::make('subregion'),
                    ])->columns(2),
                Section::make('Dialling / Currency')
                    ->schema([
                        TextEntry::make('phone_code')->label('Phone Code'),
                        TextEntry::make('calling_codes')
                            ->badge()
                            ->state(fn (AddressCountry $record): array => $record->calling_codes ?? []),
                        TextEntry::make('default_currency_code'),
                        TextEntry::make('currency_codes')
                            ->badge()
                            ->state(fn (AddressCountry $record): array => $record->currency_codes ?? []),
                    ])->columns(2),
                Section::make('Locale')
                    ->schema([
                        TextEntry::make('language_codes')
                            ->badge()
                            ->state(fn (AddressCountry $record): array => $record->language_codes ?? []),
                        TextEntry::make('timezones')
                            ->state(fn (AddressCountry $record): string => $record->timezones !== null
                                ? implode(', ', $record->timezones)
                                : '-'),
                    ])->columns(1),
                Section::make('Coordinates')
                    ->schema([
                        TextEntry::make('capital'),
                        TextEntry::make('capital_latitude'),
                        TextEntry::make('capital_longitude'),
                        TextEntry::make('latitude'),
                        TextEntry::make('longitude'),
                        TextEntry::make('top_level_domains')
                            ->badge()
                            ->state(fn (AddressCountry $record): array => $record->top_level_domains ?? []),
                    ])->columns(3),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        $country = $schema->getRecord();

        return $schema
            ->schema([
                Section::make('Identity')
                    ->schema([
                        TextInput::make('iso2')
                            ->label('ISO2')
                            ->required()
                            ->maxLength(2)
                            ->disabled(fn (): bool => self::isReadOnly() || $country !== null),
                        TextInput::make('iso3')
                            ->label('ISO3')
                            ->maxLength(3)
                            ->disabled(fn (): bool => self::isReadOnly() || $country !== null),
                        TextInput::make('name')->required(),
                        TextInput::make('official_name')->label('Official Name'),
                        TextInput::make('native_name')->label('Native Name'),
                        TextInput::make('phone_code')->label('Phone Code'),
                        TextInput::make('default_currency_code')->label('Default Currency Code'),
                    ])->columns(2),
                Section::make('Metadata')
                    ->schema([
                        Select::make('entity_type')
                            ->options([
                                'country' => 'Country',
                                'territory' => 'Territory',
                                'dependent' => 'Dependent',
                                'special' => 'Special',
                                'disputed' => 'Disputed',
                            ]),
                        Toggle::make('is_independent')
                            ->label('Independent'),
                        TextInput::make('region'),
                        TextInput::make('subregion'),
                    ])->columns(2),
            ])
            ->disabled(self::isReadOnly());
    }

    public static function getPages(): array
    {
        $pages = [
            'index' => ListAddressCountries::route('/'),
            'view' => ViewAddressCountry::route('/{record}'),
        ];

        if (! self::isReadOnly()) {
            $pages['edit'] = EditAddressCountry::route('/{record}/edit');
        }

        return $pages;
    }

    public static function isReadOnly(): bool
    {
        return (bool) config('filament-addressing.resources.countries.read_only', true);
    }
}
