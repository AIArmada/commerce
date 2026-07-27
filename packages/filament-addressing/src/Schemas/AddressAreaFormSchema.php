<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Schemas;

use AIArmada\Addressing\Contracts\CountryAddressProfile;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\AddressAreaHierarchy;
use AIArmada\Addressing\Support\CountryAddressProfileResolver;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AddressAreaFormSchema
{
    public static function form(Schema $schema): Schema
    {
        $record = $schema->getRecord();
        $currentArea = $record instanceof AddressArea ? $record : null;
        $currentAreaId = $currentArea?->getKey();

        return $schema
            ->schema([
                Section::make('Identity')
                    ->schema([
                        Select::make('country_id')
                            ->label('Country')
                            ->options(
                                config('filament-addressing.resources.countries.model', AddressCountry::class)::query()
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (AddressCountry $c): array => [$c->id => "{$c->iso2} — {$c->name}"])
                                    ->toArray(),
                            )
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (callable $set) => $set('parent_id', null)),
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('native_name')
                            ->label('Native Name')
                            ->maxLength(255),
                        TextInput::make('code')
                            ->label('Code')
                            ->maxLength(100),
                    ])->columns(2),
                Section::make('Classification')
                    ->schema([
                        TextInput::make('type')
                            ->label('Type')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g. state, province, city'),
                        TextInput::make('level')
                            ->label('Level')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(10),
                        TextInput::make('hierarchy_type')
                            ->label('Hierarchy Type')
                            ->placeholder('e.g. postal, administrative')
                            ->maxLength(50)
                            ->default(fn (callable $get): ?string => $currentArea?->ancestors()->first()?->pivot?->getAttribute('hierarchy_type')
                                ?? self::inferHierarchyType($get('country_id')))
                            ->helperText('Used to distinguish parallel geographic hierarchies.'),
                        Select::make('parent_id')
                            ->label('Parent')
                            ->options(
                                fn (callable $get): array => AddressAreaHierarchy::parentOptions(
                                    $get('country_id'),
                                    $currentAreaId,
                                ),
                            )
                            ->searchable()
                            ->placeholder('None (top-level)'),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])->columns(2),
                Section::make('Coordinates')
                    ->schema([
                        TextInput::make('latitude')
                            ->label('Latitude')
                            ->numeric()
                            ->step(0.000001),
                        TextInput::make('longitude')
                            ->label('Longitude')
                            ->numeric()
                            ->step(0.000001),
                    ])->columns(2),
                Section::make('Source')
                    ->schema([
                        TextInput::make('source')
                            ->label('Source')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('source_id')
                            ->label('Source ID')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('parent_source_id')
                            ->label('Parent Source ID')
                            ->maxLength(255),
                    ])->columns(2)
                    ->visible(fn (): bool => (bool) config('filament-addressing.features.show_source_payload')),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Identity')
                    ->schema([
                        TextEntry::make('country.name')->label('Country'),
                        TextEntry::make('type')->badge(),
                        TextEntry::make('level'),
                        TextEntry::make('name'),
                        TextEntry::make('native_name'),
                        TextEntry::make('code'),
                        TextEntry::make('slug'),
                        IconEntry::make('is_active')
                            ->boolean(),
                    ])->columns(3),
                Section::make('Hierarchy')
                    ->schema([
                        TextEntry::make('parent.name')->label('Parent'),
                        TextEntry::make('parent_source_id')->label('Parent Source ID'),
                    ])->columns(2),
                Section::make('Coordinates')
                    ->schema([
                        TextEntry::make('latitude'),
                        TextEntry::make('longitude'),
                    ])->columns(2),
                Section::make('Source')
                    ->schema([
                        TextEntry::make('source'),
                        TextEntry::make('source_id'),
                        TextEntry::make('synced_at')->dateTime(),
                    ])->columns(2),
            ]);
    }

    private static function inferHierarchyType(mixed $countryId): ?string
    {
        if (! is_string($countryId) || mb_trim($countryId) === '') {
            return null;
        }

        $country = config('filament-addressing.resources.countries.model', AddressCountry::class)::query()
            ->find($countryId);

        if (! $country instanceof AddressCountry || ! is_string($country->iso2)) {
            return null;
        }

        $provider = app(CountryAddressProfileResolver::class)->resolve($country->iso2);

        if (! $provider instanceof CountryAddressProfile) {
            return null;
        }

        $hierarchies = $provider->addressHierarchies();

        if ($hierarchies === []) {
            return null;
        }

        return $hierarchies[0]->key;
    }
}
