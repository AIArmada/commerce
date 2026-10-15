<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Schemas;

use AIArmada\Addressing\Contracts\CountryAddressProfile;
use AIArmada\Addressing\Data\AddressHierarchyDefinition;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\AddressAreaHierarchy;
use AIArmada\Addressing\Support\CountryAddressProfileResolver;
use AIArmada\Addressing\Support\ModelResolver;
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
        $isSourceManaged = $currentArea instanceof AddressArea && $currentArea->source !== 'manual';

        return $schema
            ->schema([
                Section::make('Identity')
                    ->schema([
                        Select::make('country_id')
                            ->label('Country')
                            ->options(
                                config('filament-addressing.resources.countries.model', AddressCountry::class)::query()
                                    ->orderBy('name')
                                    ->get(['id', 'iso2', 'name'])
                                    ->mapWithKeys(fn (AddressCountry $c): array => [$c->id => "{$c->iso2} — {$c->name}"])
                                    ->toArray(),
                            )
                            ->searchable()
                            ->required()
                            ->disabled($isSourceManaged)
                            ->live()
                            ->afterStateUpdated(function (mixed $state, callable $set): void {
                                $set('parent_id', null);

                                $hierarchyOptions = self::hierarchyOptions($state);
                                $set('hierarchy_type', count($hierarchyOptions) === 1 ? array_key_first($hierarchyOptions) : null);
                            }),
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
                    ])->columns(2)
                    ->disabled($isSourceManaged),
                Section::make('Classification')
                    ->schema([
                        TextInput::make('type')
                            ->label('Type')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g. state, province, city')
                            ->disabled($isSourceManaged),
                        TextInput::make('level')
                            ->label('Level')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(10)
                            ->disabled($isSourceManaged),
                        Select::make('hierarchy_type')
                            ->label('Hierarchy Type')
                            ->options(fn (callable $get): array => self::hierarchyOptions($get('country_id')))
                            ->searchable()
                            ->native(false)
                            ->placeholder('Choose a hierarchy')
                            ->default(fn (callable $get): ?string => self::defaultHierarchyType($currentArea, $get('country_id')))
                            ->helperText(fn (callable $get): ?string => count(self::hierarchyOptions($get('country_id'))) > 1
                                ? 'Choose the hierarchy for the parent relationship.'
                                : 'Used to distinguish parallel geographic hierarchies.')
                            ->visible(fn (callable $get): bool => self::hierarchyOptions($get('country_id')) !== [])
                            ->required(fn (callable $get): bool => self::hasParent($get('parent_id')) && ! $isSourceManaged)
                            ->rules(['nullable', 'string', 'max:50'])
                            ->disabled($isSourceManaged),
                        TextInput::make('hierarchy_type')
                            ->label('Hierarchy Type')
                            ->placeholder('e.g. postal, administrative')
                            ->maxLength(50)
                            ->default(fn (callable $get): ?string => self::defaultHierarchyType($currentArea, $get('country_id')))
                            ->helperText('Enter a custom hierarchy type for this country.')
                            ->visible(fn (callable $get): bool => self::hierarchyOptions($get('country_id')) === [])
                            ->required(fn (callable $get): bool => self::hasParent($get('parent_id')) && ! $isSourceManaged)
                            ->disabled($isSourceManaged),
                        Select::make('parent_id')
                            ->label('Parent')
                            ->options(
                                fn (callable $get): array => AddressAreaHierarchy::parentOptions(
                                    $get('country_id'),
                                    $currentAreaId,
                                ),
                            )
                            ->searchable()
                            ->placeholder('None (top-level)')
                            ->disabled($isSourceManaged),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText($isSourceManaged
                                ? 'Provider-managed areas may be reactivated the next time geography data is seeded.'
                                : null),
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
                    ])->columns(2)
                    ->disabled($isSourceManaged),
                Section::make('Source')
                    ->schema([
                        TextInput::make('source')
                            ->label('Source')
                            ->disabled(),
                        TextInput::make('source_id')
                            ->label('Source ID')
                            ->disabled(),
                        TextInput::make('parent_source_id')
                            ->label('Parent Source ID')
                            ->disabled(),
                    ])->columns(2),
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
                        TextEntry::make('status_warning')
                            ->label('Status warning')
                            ->state(static function (AddressArea $record): string {
                                $warnings = [];

                                if (! $record->is_active) {
                                    $warnings[] = 'This area is inactive; existing references still point to it.';
                                }

                                if ($record->source !== 'manual') {
                                    $warnings[] = 'Provider-managed details may be restored on the next geography seed.';
                                }

                                return implode(' ', $warnings);
                            })
                            ->color('warning')
                            ->visible(fn (AddressArea $record): bool => ! $record->is_active || $record->source !== 'manual'),
                    ])->columns(2),
                Section::make('Source Payload')
                    ->schema([
                        TextEntry::make('source_payload')
                            ->formatStateUsing(static fn (mixed $state): string => is_array($state)
                                ? (json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '')
                                : (string) ($state ?? '')),
                        TextEntry::make('metadata')
                            ->formatStateUsing(static fn (mixed $state): string => is_array($state)
                                ? (json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '')
                                : (string) ($state ?? '')),
                    ])
                    ->visible(fn (): bool => (bool) config('filament-addressing.features.show_source_payload')),
            ]);
    }

    /** @return array<string, string> */
    private static function hierarchyOptions(mixed $countryId): array
    {
        if (! is_string($countryId) || mb_trim($countryId) === '') {
            return [];
        }

        $country = config('filament-addressing.resources.countries.model', AddressCountry::class)::query()
            ->find($countryId);

        if (! $country instanceof AddressCountry || ! is_string($country->iso2)) {
            return [];
        }

        $provider = app(CountryAddressProfileResolver::class)->resolve($country->iso2);

        if (! $provider instanceof CountryAddressProfile) {
            return [];
        }

        return collect($provider->addressHierarchies())
            ->mapWithKeys(static fn (AddressHierarchyDefinition $hierarchy): array => [$hierarchy->key => $hierarchy->label])
            ->all();
    }

    /**
     * The hierarchy to pre-select when there is only one sensible value:
     * the area's single existing contains-hierarchy, else the sole
     * configured hierarchy for its country. Anything ambiguous stays
     * empty so the operator picks explicitly.
     */
    public static function defaultHierarchyType(?AddressArea $area, mixed $countryId): ?string
    {
        return self::currentHierarchyType($area)
            ?? self::soleHierarchyType(self::hierarchyOptions($countryId));
    }

    /** @param array<string, string> $options */
    private static function soleHierarchyType(array $options): ?string
    {
        return count($options) === 1 ? array_key_first($options) : null;
    }

    private static function currentHierarchyType(?AddressArea $area): ?string
    {
        if (! $area instanceof AddressArea) {
            return null;
        }

        $hierarchyTypes = ModelResolver::areaRelationshipClass()::query()
            ->where('child_address_area_id', $area->getKey())
            ->where('relationship_type', 'contains')
            ->pluck('hierarchy_type')
            ->filter(static fn (mixed $hierarchyType): bool => is_string($hierarchyType) && mb_trim($hierarchyType) !== '')
            ->unique()
            ->values();

        return $hierarchyTypes->count() === 1 ? $hierarchyTypes->first() : null;
    }

    private static function hasParent(mixed $parentId): bool
    {
        return is_string($parentId) && mb_trim($parentId) !== '';
    }
}
