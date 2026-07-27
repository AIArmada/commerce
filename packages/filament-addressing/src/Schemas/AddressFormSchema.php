<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Schemas;

use AIArmada\Addressing\Contracts\CountryAddressProfile;
use AIArmada\Addressing\Data\AddressHierarchyDefinition;
use AIArmada\Addressing\Data\AddressLevelDefinition;
use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\AddressAreaStateBridge;
use AIArmada\Addressing\Support\CountryAddressProfileResolver;
use AIArmada\Addressing\Support\ModelResolver;
use AIArmada\FilamentAddressing\Rules\AddressAreasBelongToCountry;
use AIArmada\FilamentAddressing\Rules\StateBelongsToCountry;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class AddressFormSchema
{
    public static function make(string $prefix = '', ?Address $record = null): array
    {
        $fields = [];
        $assignmentValues = $record?->areaAssignments()->pluck('address_area_id', 'role')->toArray() ?? [];
        $assignmentRoles = self::assignmentRoles();

        $fields[] = Select::make($prefix . 'country_code')
            ->label('Country')
            ->options(
                config('filament-addressing.resources.countries.model', AddressCountry::class)::query()
                    ->orderBy('name')
                    ->get()
                    ->mapWithKeys(fn (AddressCountry $country): array => [
                        $country->iso2 => "{$country->iso2} — {$country->name}",
                    ])
                    ->toArray(),
            )
            ->searchable()
            ->required()
            ->live()
            ->afterStateUpdated(function (callable $set) use ($prefix): void {
                $set($prefix . 'state_id', null);
                $set($prefix . 'area_assignments', []);
            });

        $fields[] = TextInput::make($prefix . 'label')
            ->label('Label')
            ->maxLength(255);

        $fields[] = TextInput::make($prefix . 'line1')
            ->label('Line 1')
            ->maxLength(255);

        $fields[] = TextInput::make($prefix . 'line2')
            ->label('Line 2')
            ->maxLength(255);

        $fields[] = TextInput::make($prefix . 'city')
            ->label('City / Locality (free text)')
            ->maxLength(255);

        $fields[] = Select::make($prefix . 'state_id')
            ->label('State / Federal Territory')
            ->options(fn (callable $get): array => ModelResolver::stateClass()::query()
                ->when($get($prefix . 'country_code'), fn ($query, string $countryCode) => $query->whereHas('country', fn ($countries) => $countries->where('iso2', $countryCode)))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray())
            ->searchable()
            ->rules(fn (callable $get): array => [new StateBelongsToCountry($get($prefix . 'country_code'))])
            ->visible(fn (callable $get): bool => self::countryHasStates($get($prefix . 'country_code')))
            ->live();

        foreach ($assignmentRoles as $role) {
            $areaClass = config('filament-addressing.resources.areas.model', AddressArea::class);
            $field = $prefix . 'area_assignments.' . $role;

            $fields[] = Select::make($field)
                ->label(function (callable $get) use ($prefix, $role): string {
                    $definition = self::definitionForRole(self::nullableString($get($prefix . 'country_code')), $role);

                    return $definition['level']->label ?? str_replace('_', ' ', ucfirst($role));
                })
                ->getSearchResultsUsing(function (string $search, callable $get) use ($prefix, $areaClass, $role): array {
                    $countryCode = $get($prefix . 'country_code');

                    if (! is_string($countryCode) || mb_trim($countryCode) === '') {
                        return [];
                    }

                    $definition = self::definitionForRole($countryCode, $role);

                    if ($definition === null) {
                        return [];
                    }

                    $query = $areaClass::query()
                        ->where('country_code', mb_strtoupper($countryCode))
                        ->where('is_active', true)
                        ->when(self::areaTypes($definition['level']) !== [], fn ($query) => $query->whereIn('type', self::areaTypes($definition['level'])))
                        ->when(self::areaLevels($definition['level']) !== [], fn ($query) => $query->whereIn('level', self::areaLevels($definition['level'])));

                    $parentId = self::parentId($definition, $get, $prefix);

                    if ($definition['level']->parentKey !== null && $parentId === null) {
                        return [];
                    }

                    if ($parentId !== null) {
                        $query->whereHas('ancestors', function ($ancestors) use ($parentId, $definition): void {
                            $ancestors
                                ->whereKey($parentId)
                                ->where(
                                    config('addressing.tables.area_relationships', 'address_area_relationships') . '.hierarchy_type',
                                    self::hierarchyType($definition),
                                )
                                ->where(
                                    config('addressing.tables.area_relationships', 'address_area_relationships') . '.relationship_type',
                                    'contains',
                                )
                                ->where(function ($query): void {
                                    $query->whereNull('valid_from')->orWhereDate('valid_from', '<=', now());
                                })
                                ->where(function ($query): void {
                                    $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', now());
                                });
                        });
                    }

                    return $query
                        ->where(function ($searchQuery) use ($search): void {
                            $searchQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('slug', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        })
                        ->orderBy('name')
                        ->limit(50)
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->getOptionLabelUsing(fn (mixed $value): ?string => $value === null ? null : $areaClass::query()->whereKey($value)->value('name'))
                ->searchable()
                ->rules(fn (callable $get): array => [new AddressAreasBelongToCountry($get($prefix . 'country_code'))])
                ->default($assignmentValues[$role] ?? null)
                ->dehydrated(false)
                ->visible(fn (callable $get): bool => self::definitionForRole(self::nullableString($get($prefix . 'country_code')), $role) !== null)
                ->live();
        }

        $fields[] = TextInput::make($prefix . 'postcode')
            ->label('Postcode')
            ->maxLength(20);

        return $fields;
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value === '' ? null : $value;
    }

    /** @return list<string> */
    private static function assignmentRoles(): array
    {
        $roles = [];

        foreach (config('addressing.geography.providers', []) as $providerClass) {
            if (! is_string($providerClass)) {
                continue;
            }

            $provider = app($providerClass);

            if (! $provider instanceof CountryAddressProfile) {
                continue;
            }

            foreach ($provider->addressHierarchies() as $hierarchy) {
                foreach ($hierarchy->levels as $level) {
                    if ($level->kind === 'state') {
                        continue;
                    }

                    $roles[] = self::roleForLevel($hierarchy, $level);
                }
            }
        }

        return array_values(array_unique($roles));
    }

    /** @return array{hierarchy: AddressHierarchyDefinition, level: AddressLevelDefinition}|null */
    private static function definitionForRole(?string $countryCode, string $role): ?array
    {
        if ($countryCode === null) {
            return null;
        }

        $hierarchies = app(CountryAddressProfileResolver::class)->hierarchies($countryCode);

        foreach ($hierarchies as $hierarchy) {
            foreach ($hierarchy->levels as $level) {
                if ($level->kind !== 'state' && self::roleForLevel($hierarchy, $level) === $role) {
                    return ['hierarchy' => $hierarchy, 'level' => $level];
                }
            }
        }

        return null;
    }

    private static function roleForLevel(AddressHierarchyDefinition $hierarchy, AddressLevelDefinition $level): string
    {
        return $level->assignmentRole ?? "{$hierarchy->key}_{$level->key}";
    }

    /** @return list<string> */
    private static function areaTypes(AddressLevelDefinition $level): array
    {
        return $level->areaTypes !== []
            ? $level->areaTypes
            : ($level->areaType !== null ? [$level->areaType] : []);
    }

    /** @return list<int> */
    private static function areaLevels(AddressLevelDefinition $level): array
    {
        return $level->areaLevels !== []
            ? $level->areaLevels
            : ($level->areaLevel !== null ? [$level->areaLevel] : []);
    }

    /** @param array{hierarchy: AddressHierarchyDefinition, level: AddressLevelDefinition} $definition */
    private static function parentId(array $definition, callable $get, string $prefix): ?string
    {
        $parentKey = $definition['level']->parentKey;

        if ($parentKey === null) {
            return null;
        }

        foreach ($definition['hierarchy']->levels as $level) {
            if ($level->key === $parentKey) {
                if ($level->kind === 'state') {
                    return AddressAreaStateBridge::areaIdForState(
                        self::nullableString($get($prefix . 'state_id')),
                        self::hierarchyType($definition),
                    );
                }

                return self::nullableString($get($prefix . 'area_assignments.' . self::roleForLevel($definition['hierarchy'], $level)));
            }
        }

        return null;
    }

    /** @param array{hierarchy: AddressHierarchyDefinition, level: AddressLevelDefinition} $definition */
    private static function hierarchyType(array $definition): string
    {
        return $definition['level']->hierarchyType ?? $definition['hierarchy']->key;
    }

    private static function countryHasStates(mixed $countryCode): bool
    {
        $countryCode = self::nullableString($countryCode);

        if ($countryCode === null) {
            return false;
        }

        return ModelResolver::stateClass()::query()
            ->whereHas('country', fn ($query) => $query->where('iso2', mb_strtoupper($countryCode)))
            ->exists();
    }
}
