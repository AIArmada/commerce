<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources;

use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\FilamentAddressing\Resources\AddressCountryResource\Pages\EditAddressCountry;
use AIArmada\FilamentAddressing\Resources\AddressCountryResource\Pages\ListAddressCountries;
use AIArmada\FilamentAddressing\Resources\AddressCountryResource\Pages\ViewAddressCountry;
use AIArmada\FilamentAddressing\Tables\AddressCountryTable;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AddressCountryResource extends Resource
{
    protected static ?string $slug = 'countries';

    protected static ?string $model = AddressCountry::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-globe-alt';

    public static function getNavigationLabel(): string
    {
        return 'Countries';
    }

    public static function getModelLabel(): string
    {
        return 'Country';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Countries';
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-addressing.navigation.group');
    }

    public static function getNavigationIcon(): BackedEnum | string | null
    {
        return config('filament-addressing.navigation.icons.countries', parent::getNavigationIcon());
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
                        TextEntry::make('native'),
                        TextEntry::make('emoji'),
                    ])->columns(3),
                Section::make('Classification')
                    ->schema([
                        TextEntry::make('region'),
                        TextEntry::make('subregion'),
                    ])->columns(2),
                Section::make('Dialling / Currency')
                    ->schema([
                        TextEntry::make('phone_code')->label('Phone Code'),
                        TextEntry::make('currencies')
                            ->state(fn (AddressCountry $record): string => $record->currencies->pluck('code')->implode(', ')),
                    ])->columns(2),
                Section::make('Locale')
                    ->schema([
                        TextEntry::make('timezones')
                            ->state(fn (AddressCountry $record): string => $record->timezones->pluck('name')->implode(', ')),
                    ])->columns(1),
                Section::make('Coordinates')
                    ->schema([
                        TextEntry::make('capital'),
                        TextEntry::make('tld')->label('Top-Level Domain'),
                        TextEntry::make('latitude'),
                        TextEntry::make('longitude'),
                        TextEntry::make('emojiU')->label('Emoji Unicode'),
                    ])->columns(3),
                Section::make('Translations')
                    ->schema([
                        TextEntry::make('translations')
                            ->formatStateUsing(fn (mixed $state): string => is_array($state)
                                ? (json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '')
                                : (string) ($state ?? '')),
                    ]),
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
                        TextInput::make('native')->label('Native Name'),
                        TextInput::make('phone_code')->label('Phone Code'),
                    ])->columns(2),
                Section::make('Metadata')
                    ->schema([
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
        return (bool) config('filament-addressing.resources.countries.read_only', true)
            || ! (bool) config('filament-addressing.features.country_editing', false);
    }
}
