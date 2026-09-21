<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Contracts\CountryAddressProfile;
use AIArmada\Addressing\Data\AddressHierarchyDefinition;
use AIArmada\Addressing\Data\AddressLevelDefinition;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;
use AIArmada\FilamentAddressing\Schemas\AddressFormSchema;

beforeEach(function (): void {
    app(SeedAddressCountriesAction::class)->execute();
});

it('builds area assignment fields from the configured country profiles', function (): void {
    $components = collect(AddressFormSchema::make());
    $names = $components
        ->map(static fn ($component): string => $component->getName())
        ->all();

    expect($names)
        ->toContain('area_assignments.postal_locality')
        ->toContain('area_assignments.administrative_district')
        ->toContain('area_assignments.administrative_subdivision')
        ->not->toContain('postal_area_id')
        ->not->toContain('administrative_district_id');

    expect($components->first(fn ($component): bool => $component->getName() === 'state_id')->isRequired())->toBeFalse();
});

it('searches profile-defined areas without requiring imported role metadata', function (): void {
    $profile = new class implements CountryAddressProfile
    {
        public function countryCode(): string
        {
            return 'MY';
        }

        public function addressHierarchies(): array
        {
            return [new AddressHierarchyDefinition('profile', 'Profile', [
                new AddressLevelDefinition(
                    key: 'locality',
                    label: 'Profile locality',
                    kind: 'area',
                    hierarchyType: 'profile',
                    areaTypes: ['locality'],
                    areaLevel: 1,
                    assignmentRole: 'profile_locality',
                ),
            ])];
        }
    };
    config()->set('addressing.geography.providers', [get_class($profile)]);
    $area = AddressArea::query()->create([
        'country_code' => 'MY',
        'type' => 'locality',
        'level' => 1,
        'name' => 'Profile locality',
        'slug' => 'profile-locality',
        'source' => 'test',
        'source_id' => 'profile-locality',
    ]);

    $field = collect(AddressFormSchema::make())
        ->first(fn ($component): bool => $component->getName() === 'area_assignments.profile_locality');
    $callback = (new ReflectionProperty($field, 'getSearchResultsUsing'))->getValue($field);

    expect($area->roles()->exists())->toBeFalse()
        ->and($callback)->toBeInstanceOf(Closure::class)
        ->and($callback('Profile locality', static fn (string $path): ?string => $path === 'country_code' ? 'MY' : null))
        ->toBe([$area->getKey() => 'Profile locality']);
});

it('extracts area assignments from raw form state', function (): void {
    expect(AddressFormSchema::extractAreaAssignments([
        'country_code' => 'MY',
        'area_assignments' => [
            'profile_locality' => 'area-1',
            'empty_role' => null,
            'junk' => 123,
            7 => 'area-2',
        ],
    ]))->toBe([
        'profile_locality' => 'area-1',
        'empty_role' => null,
    ]);

    expect(AddressFormSchema::extractAreaAssignments([
        'billing_area_assignments' => ['profile_locality' => 'area-9'],
    ], 'billing_'))->toBe(['profile_locality' => 'area-9']);

    expect(AddressFormSchema::extractAreaAssignments(['country_code' => 'MY']))->toBe([])
        ->and(AddressFormSchema::extractAreaAssignments(['area_assignments' => 'nope']))->toBe([]);
});

it('clears state-dependent areas when the state changes', function (): void {
    cascadeProfile();

    $field = collect(AddressFormSchema::make())
        ->first(fn ($component): bool => $component->getName() === 'state_id');
    $callbacks = (new ReflectionProperty($field, 'afterStateUpdated'))->getValue($field);

    expect($callbacks)->toHaveCount(1);

    $cleared = [];
    $callbacks[0](
        static function (string $path, mixed $value) use (&$cleared): void {
            $cleared[$path] = $value;
        },
        static fn (string $path): ?string => $path === 'country_code' ? 'MY' : null,
    );

    expect($cleared)->toBe([
        'area_assignments.test_district' => null,
        'area_assignments.test_subdistrict' => null,
    ]);
});

it('clears only child roles when a parent area changes', function (): void {
    cascadeProfile();

    $fields = collect(AddressFormSchema::make())->keyBy(fn ($component): string => $component->getName());
    $get = static fn (string $path): ?string => $path === 'country_code' ? 'MY' : null;

    $cleared = [];
    $set = static function (string $path, mixed $value) use (&$cleared): void {
        $cleared[$path] = $value;
    };

    $districtCallbacks = (new ReflectionProperty($fields->get('area_assignments.test_district'), 'afterStateUpdated'))->getValue($fields->get('area_assignments.test_district'));
    expect($districtCallbacks)->toHaveCount(1);
    $districtCallbacks[0]($set, $get);

    expect($cleared)->toBe(['area_assignments.test_subdistrict' => null]);

    $cleared = [];
    $zoneCallbacks = (new ReflectionProperty($fields->get('area_assignments.test_zone'), 'afterStateUpdated'))->getValue($fields->get('area_assignments.test_zone'));
    expect($zoneCallbacks)->toHaveCount(1);
    $zoneCallbacks[0]($set, $get);

    expect($cleared)->toBe([]);
});

it('narrows region-parented options to the selected district', function (): void {
    $ids = narrowedWorld();

    $field = collect(AddressFormSchema::make())
        ->first(fn ($component): bool => $component->getName() === 'area_assignments.narrow_subdivision');
    $callback = (new ReflectionProperty($field, 'getSearchResultsUsing'))->getValue($field);
    $results = $callback('mukim', static fn (string $path): ?string => match ($path) {
        'country_code' => 'MY',
        'state_id' => $ids['state'],
        'area_assignments.narrow_district' => $ids['district'],
        default => null,
    });

    expect($results)->toBe([
        $ids['mukim_one'] => 'Mukim One',
        $ids['mukim_two'] => 'Mukim Two',
    ]);
});

it('falls back to state scope without a district selection', function (): void {
    $ids = narrowedWorld();

    $field = collect(AddressFormSchema::make())
        ->first(fn ($component): bool => $component->getName() === 'area_assignments.narrow_subdivision');
    $callback = (new ReflectionProperty($field, 'getSearchResultsUsing'))->getValue($field);
    $results = $callback('mukim', static fn (string $path): ?string => match ($path) {
        'country_code' => 'MY',
        'state_id' => $ids['state'],
        default => null,
    });

    expect($results)->toBe([
        $ids['mukim_one'] => 'Mukim One',
        $ids['mukim_three'] => 'Mukim Three',
        $ids['mukim_two'] => 'Mukim Two',
    ]);
});

it('keeps state scope for siblings without district links', function (): void {
    $ids = narrowedWorld();

    $field = collect(AddressFormSchema::make())
        ->first(fn ($component): bool => $component->getName() === 'area_assignments.narrow_zone');
    $callback = (new ReflectionProperty($field, 'getSearchResultsUsing'))->getValue($field);
    $results = $callback('zone', static fn (string $path): ?string => match ($path) {
        'country_code' => 'MY',
        'state_id' => $ids['state'],
        'area_assignments.narrow_district' => $ids['district'],
        default => null,
    });

    expect($results)->toBe([$ids['zone'] => 'Zone One']);
});

it('clears narrowed successors when an earlier level changes', function (): void {
    narrowedWorld();

    $fields = collect(AddressFormSchema::make())->keyBy(fn ($component): string => $component->getName());
    $callbacks = (new ReflectionProperty($fields->get('area_assignments.narrow_district'), 'afterStateUpdated'))->getValue($fields->get('area_assignments.narrow_district'));

    expect($callbacks)->toHaveCount(1);

    $cleared = [];
    $callbacks[0](
        static function (string $path, mixed $value) use (&$cleared): void {
            $cleared[$path] = $value;
        },
        static fn (string $path): ?string => $path === 'country_code' ? 'MY' : null,
    );

    expect($cleared)->toBe([
        'area_assignments.narrow_subdivision' => null,
        'area_assignments.narrow_zone' => null,
    ]);
});

/**
 * @return array{state: string, district: string, mukim_one: string, mukim_two: string, mukim_three: string, zone: string}
 */
function narrowedWorld(): array
{
    $profile = new class implements CountryAddressProfile
    {
        public function countryCode(): string
        {
            return 'MY';
        }

        public function addressHierarchies(): array
        {
            return [new AddressHierarchyDefinition('geo', 'Geo', [
                new AddressLevelDefinition(key: 'state', label: 'State', kind: 'state'),
                new AddressLevelDefinition(key: 'district', label: 'District', kind: 'area', parentKey: 'state', assignmentRole: 'narrow_district', areaTypes: ['district'], areaLevel: 2),
                new AddressLevelDefinition(key: 'subdivision', label: 'Subdivision', kind: 'area', parentKey: 'state', assignmentRole: 'narrow_subdivision', areaTypes: ['mukim'], areaLevel: 3),
                new AddressLevelDefinition(key: 'zone', label: 'Zone', kind: 'area', parentKey: 'state', assignmentRole: 'narrow_zone', areaTypes: ['zone'], areaLevel: 3),
            ])];
        }
    };
    config()->set('addressing.geography.providers', [get_class($profile)]);

    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $state = State::query()->create(['country_id' => $country->id, 'name' => 'Johor', 'label' => 'Johor']);

    $makeArea = static fn (string $type, int $level, string $name, string $slug): AddressArea => AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'type' => $type,
        'level' => $level,
        'name' => $name,
        'slug' => $slug,
        'source' => 'test',
        'source_id' => $slug,
    ]);

    $stateArea = $makeArea('state', 1, 'Johor', 'johor');
    $district = $makeArea('district', 2, 'Batu Pahat', 'batu-pahat');
    $mukimOne = $makeArea('mukim', 3, 'Mukim One', 'mukim-one');
    $mukimTwo = $makeArea('mukim', 3, 'Mukim Two', 'mukim-two');
    $mukimThree = $makeArea('mukim', 3, 'Mukim Three', 'mukim-three');
    $zone = $makeArea('zone', 3, 'Zone One', 'zone-one');

    AddressAreaStateLink::query()->create(['address_area_id' => $stateArea->id, 'state_id' => $state->id]);

    $link = static function (AddressArea $parent, AddressArea $child): void {
        AddressAreaRelationship::query()->create([
            'parent_address_area_id' => $parent->id,
            'child_address_area_id' => $child->id,
            'relationship_type' => 'contains',
            'hierarchy_type' => 'geo',
        ]);
    };

    $link($stateArea, $district);
    $link($stateArea, $mukimOne);
    $link($stateArea, $mukimTwo);
    $link($stateArea, $mukimThree);
    $link($stateArea, $zone);
    $link($district, $mukimOne);
    $link($district, $mukimTwo);

    return [
        'state' => (string) $state->id,
        'district' => (string) $district->id,
        'mukim_one' => (string) $mukimOne->id,
        'mukim_two' => (string) $mukimTwo->id,
        'mukim_three' => (string) $mukimThree->id,
        'zone' => (string) $zone->id,
    ];
}

function cascadeProfile(): void
{
    $profile = new class implements CountryAddressProfile
    {
        public function countryCode(): string
        {
            return 'MY';
        }

        public function addressHierarchies(): array
        {
            return [new AddressHierarchyDefinition('geo', 'Geo', [
                new AddressLevelDefinition(key: 'state', label: 'State', kind: 'state'),
                new AddressLevelDefinition(key: 'district', label: 'District', kind: 'area', parentKey: 'state', assignmentRole: 'test_district'),
                new AddressLevelDefinition(key: 'subdistrict', label: 'Subdistrict', kind: 'area', parentKey: 'district', assignmentRole: 'test_subdistrict'),
                new AddressLevelDefinition(key: 'zone', label: 'Zone', kind: 'area', assignmentRole: 'test_zone'),
            ])];
        }
    };
    config()->set('addressing.geography.providers', [get_class($profile)]);
}
