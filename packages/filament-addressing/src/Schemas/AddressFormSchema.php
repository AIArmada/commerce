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
use AIArmada\CommerceSupport\Support\LikeSearch;
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
                    ->get(['iso2', 'name'])
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
            ->label(function (callable $get) use ($prefix): string {
                $level = app(CountryAddressProfileResolver::class)
                    ->stateLevel(self::nullableString($get($prefix . 'country_code')));

                return $level?->label ?? 'State / Province';
            })
            ->options(function (callable $get) use ($prefix): array {
                $countryCode = self::nullableString($get($prefix . 'country_code'));

                if ($countryCode === null) {
                    return [];
                }

                return ModelResolver::stateClass()::query()
                    ->whereHas('country', fn ($countries) => $countries->where('iso2', $countryCode))
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->toArray();
            })
            ->searchable()
            ->rules(fn (callable $get): array => [new StateBelongsToCountry($get($prefix . 'country_code'))])
            ->visible(fn (callable $get): bool => self::countryHasStates($get($prefix . 'country_code')))
            ->afterStateUpdated(function (callable $set, callable $get) use ($prefix): void {
                foreach (self::stateDependentRoles(self::nullableString($get($prefix . 'country_code'))) as $role) {
                    $set($prefix . 'area_assignments.' . $role, null);
                }
            })
            ->live();

        foreach ($assignmentRoles as $role) {
            $areaClass = config('filament-addressing.resources.areas.model', AddressArea::class);
            $field = $prefix . 'area_assignments.' . $role;

            $fields[] = Select::make($field)
                ->label(function (callable $get) use ($prefix, $role): string {
                    $definition = app(CountryAddressProfileResolver::class)->definitionForRole(self::nullableString($get($prefix . 'country_code')), $role);

                    return $definition === null ? str_replace('_', ' ', ucfirst($role)) : $definition['level']->label;
                })
                ->getSearchResultsUsing(function (string $search, callable $get) use ($prefix, $areaClass, $role): array {
                    $countryCode = $get($prefix . 'country_code');

                    if (! is_string($countryCode) || mb_trim($countryCode) === '') {
                        return [];
                    }

                    $definition = app(CountryAddressProfileResolver::class)->definitionForRole($countryCode, $role);

                    if ($definition === null) {
                        return [];
                    }

                    $query = $areaClass::query()
                        ->where('country_code', mb_strtoupper($countryCode))
                        ->where('is_active', true)
                        ->when(self::areaTypes($definition['level']) !== [], fn ($query) => $query->whereIn('type', self::areaTypes($definition['level'])))
                        ->when(self::areaLevels($definition['level']) !== [], fn ($query) => $query->whereIn('level', self::areaLevels($definition['level'])));
                    $parentId = self::parentId($definition, $get, $prefix, $areaClass);

                    if ($definition['level']->parentKey !== null && $parentId === null) {
                        return [];
                    }

                    if ($parentId !== null) {
                        $query->whereAncestorLink($parentId, self::hierarchyType($definition));
                    }

                    $needle = LikeSearch::contains($search);

                    return $query
                        ->where(function ($searchQuery) use ($needle): void {
                            LikeSearch::whereLike($searchQuery, 'name', $needle);
                            LikeSearch::orWhereLike($searchQuery, 'slug', $needle);
                            LikeSearch::orWhereLike($searchQuery, 'code', $needle);
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
                ->visible(fn (callable $get): bool => app(CountryAddressProfileResolver::class)->definitionForRole(self::nullableString($get($prefix . 'country_code')), $role) !== null)
                ->afterStateUpdated(function (callable $set, callable $get) use ($prefix, $role): void {
                    foreach (self::childRoles(self::nullableString($get($prefix . 'country_code')), $role) as $childRole) {
                        $set($prefix . 'area_assignments.' . $childRole, null);
                    }
                })
                ->live();
        }

        $fields[] = TextInput::make($prefix . 'postcode')
            ->label('Postcode')
            ->maxLength(20);

        return $fields;
    }

    /**
     * Read area assignments from raw form state.
     *
     * Area selects are dehydrated(false), so consumers must read them via
     * `$form->getRawState()` and pass the result to
     * SyncAddressAreaAssignmentsAction after saving the address.
     *
     * @param  array<string, mixed>  $rawState
     * @return array<string, string|null>
     */
    public static function extractAreaAssignments(array $rawState, string $prefix = ''): array
    {
        $assignments = $rawState[$prefix . 'area_assignments'] ?? [];

        if (! is_array($assignments)) {
            return [];
        }

        $filtered = [];

        foreach ($assignments as $role => $areaId) {
            if (is_string($role) && ($areaId === null || is_string($areaId))) {
                $filtered[$role] = $areaId;
            }
        }

        return $filtered;
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

                    $roles[] = CountryAddressProfileResolver::roleForLevel($hierarchy, $level);
                }
            }
        }

        return array_values(array_unique($roles));
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
    private static function parentId(array $definition, callable $get, string $prefix, string $areaClass): ?string
    {
        $parentKey = $definition['level']->parentKey;

        if ($parentKey === null) {
            return null;
        }

        foreach ($definition['hierarchy']->levels as $level) {
            if ($level->key === $parentKey) {
                if ($level->kind === 'state') {
                    return self::narrowedParentId($definition, $get, $prefix, $areaClass)
                        ?? AddressAreaStateBridge::areaIdForState(
                            self::nullableString($get($prefix . 'state_id')),
                            self::hierarchyType($definition),
                        );
                }

                return self::nullableString($get($prefix . 'area_assignments.' . CountryAddressProfileResolver::roleForLevel($definition['hierarchy'], $level)));
            }
        }

        return null;
    }

    /**
     * Prefer a selected intermediate area level over the declared state
     * parent when the stored links prove the narrowing is structural.
     *
     * Region-parented levels such as Malaysia's subdivisions stay reachable
     * for district-less states (KL) through the state fallback, while a
     * selected district narrows them to its own rows. Levels without a
     * matching parent/child link in the data never narrow, so unrelated
     * sibling levels keep state scope.
     *
     * @param array{hierarchy: AddressHierarchyDefinition, level: AddressLevelDefinition} $definition
     */
    private static function narrowedParentId(array $definition, callable $get, string $prefix, string $areaClass): ?string
    {
        $levels = array_values($definition['hierarchy']->levels);
        $position = null;

        foreach ($levels as $index => $level) {
            if ($level->key === $definition['level']->key) {
                $position = $index;

                break;
            }
        }

        if ($position === null) {
            return null;
        }

        for ($index = $position - 1; $index >= 0; $index--) {
            $candidate = $levels[$index];

            if ($candidate->kind !== 'area') {
                continue;
            }

            $selected = self::nullableString($get(
                $prefix . 'area_assignments.' . CountryAddressProfileResolver::roleForLevel($definition['hierarchy'], $candidate),
            ));

            if ($selected === null) {
                continue;
            }

            $query = $areaClass::query()
                ->where('is_active', true)
                ->when(self::areaTypes($definition['level']) !== [], fn ($query) => $query->whereIn('type', self::areaTypes($definition['level'])))
                ->when(self::areaLevels($definition['level']) !== [], fn ($query) => $query->whereIn('level', self::areaLevels($definition['level'])))
                ->whereAncestorLink($selected, self::hierarchyType($definition));

            if ($query->exists()) {
                return $selected;
            }
        }

        return null;
    }

    /** @param array{hierarchy: AddressHierarchyDefinition, level: AddressLevelDefinition} $definition */
    private static function hierarchyType(array $definition): string
    {
        return $definition['level']->hierarchyType ?? $definition['hierarchy']->key;
    }

    /**
     * Roles whose parent chain passes through the state level.
     *
     * @return list<string>
     */
    private static function stateDependentRoles(?string $countryCode): array
    {
        if ($countryCode === null) {
            return [];
        }

        $resolver = app(CountryAddressProfileResolver::class);
        $dependents = [];

        foreach (self::assignmentRoles() as $role) {
            $definition = $resolver->definitionForRole($countryCode, $role);

            if ($definition === null) {
                continue;
            }

            $isStateDependent = self::ancestorMatches(
                $definition['hierarchy'],
                $definition['level'],
                static fn (AddressLevelDefinition $ancestor): bool => $ancestor->kind === 'state',
            );

            if ($isStateDependent) {
                $dependents[] = $role;
            }
        }

        return $dependents;
    }

    /**
     * Roles nested under the changed role within the same hierarchy.
     *
     * @return list<string>
     */
    private static function childRoles(?string $countryCode, string $changedRole): array
    {
        if ($countryCode === null) {
            return [];
        }

        $resolver = app(CountryAddressProfileResolver::class);
        $changed = $resolver->definitionForRole($countryCode, $changedRole);

        if ($changed === null) {
            return [];
        }

        $children = [];

        foreach (self::assignmentRoles() as $role) {
            if ($role === $changedRole) {
                continue;
            }

            $definition = $resolver->definitionForRole($countryCode, $role);

            if ($definition === null || $definition['hierarchy']->key !== $changed['hierarchy']->key) {
                continue;
            }

            $parentKey = $changed['level']->key;

            if (self::ancestorMatches($definition['hierarchy'], $definition['level'], static fn (AddressLevelDefinition $ancestor): bool => $ancestor->key === $parentKey)) {
                $children[] = $role;
            } elseif (self::isNarrowedSuccessor($changed, $definition)) {
                $children[] = $role;
            }
        }

        return $children;
    }

    /**
     * Region-parented levels positioned after the changed level reset with
     * it, mirroring narrowedParentId(): a new district invalidates any
     * subdivision picked under the previous one.
     *
     * @param array{hierarchy: AddressHierarchyDefinition, level: AddressLevelDefinition} $changed
     * @param array{hierarchy: AddressHierarchyDefinition, level: AddressLevelDefinition} $definition
     */
    private static function isNarrowedSuccessor(array $changed, array $definition): bool
    {
        if ($definition['hierarchy']->key !== $changed['hierarchy']->key || $changed['level']->kind !== 'area') {
            return false;
        }

        $reachesState = self::ancestorMatches(
            $definition['hierarchy'],
            $definition['level'],
            static fn (AddressLevelDefinition $ancestor): bool => $ancestor->kind === 'state',
        );

        if (! $reachesState) {
            return false;
        }

        $changedPosition = null;
        $candidatePosition = null;

        foreach (array_values($definition['hierarchy']->levels) as $index => $level) {
            if ($level->key === $changed['level']->key) {
                $changedPosition = $index;
            }

            if ($level->key === $definition['level']->key) {
                $candidatePosition = $index;
            }
        }

        return $changedPosition !== null && $candidatePosition !== null && $changedPosition < $candidatePosition;
    }

    /** @param callable(AddressLevelDefinition): bool $matches */
    private static function ancestorMatches(AddressHierarchyDefinition $hierarchy, AddressLevelDefinition $level, callable $matches): bool
    {
        $byKey = [];

        foreach ($hierarchy->levels as $candidate) {
            $byKey[$candidate->key] = $candidate;
        }

        $visited = [];
        $current = $level;

        while (true) {
            $parentKey = $current->parentKey;

            if ($parentKey === null || isset($visited[$current->key])) {
                return false;
            }

            $visited[$current->key] = true;
            $parent = $byKey[$parentKey] ?? null;

            if (! $parent instanceof AddressLevelDefinition) {
                return false;
            }

            if ($matches($parent)) {
                return true;
            }

            $current = $parent;
        }
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
