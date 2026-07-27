<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Contracts\CountryAddressProfile;
use AIArmada\Addressing\Data\AddressHierarchyDefinition;
use AIArmada\Addressing\Data\AddressLevelDefinition;
use AIArmada\Addressing\Models\AddressArea;
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
