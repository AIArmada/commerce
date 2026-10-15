<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\ImportAddressAreasAction;
use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Contracts\CountryAddressProfile;
use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Data\AddressHierarchyDefinition;
use AIArmada\Addressing\Data\AddressLevelDefinition;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\ArrayAddressAreaSource;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource\Pages\EditAddressArea;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('area resource navigation can be disabled globally', function (): void {
    $original = config('filament-addressing.navigation.enabled', true);

    config()->set('filament-addressing.navigation.enabled', false);

    try {
        expect(AddressAreaResource::shouldRegisterNavigation())->toBeFalse();
    } finally {
        config()->set('filament-addressing.navigation.enabled', $original);
    }
});

it('area resource omits edit pages when read only', function (): void {
    $original = config('filament-addressing.resources.areas.read_only', false);

    config()->set('filament-addressing.resources.areas.read_only', true);

    try {
        expect(AddressAreaResource::getPages())->not->toHaveKey('edit');
    } finally {
        config()->set('filament-addressing.resources.areas.read_only', $original);
    }
});

it('rejects cyclic parents when editing areas', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $action = app(ImportAddressAreasAction::class);

    $action->execute(new ArrayAddressAreaSource('test', [
        new AddressAreaData(
            source: 'test',
            sourceId: 'root',
            countryCode: 'MY',
            type: 'state',
            name: 'Selangor',
        ),
        new AddressAreaData(
            source: 'test',
            sourceId: 'child',
            countryCode: 'MY',
            type: 'district',
            name: 'Petaling',
            parentSourceId: 'root',
        ),
    ]));

    $root = AddressArea::where('source', 'test')
        ->where('source_id', 'root')
        ->firstOrFail();

    $child = AddressArea::where('source', 'test')
        ->where('source_id', 'child')
        ->firstOrFail();

    $page = app(EditAddressArea::class);
    $page->record = $root;

    $method = new ReflectionMethod(EditAddressArea::class, 'mutateFormDataBeforeSave');
    $method->setAccessible(true);

    expect(function () use ($method, $page, $child): mixed {
        return $method->invoke($page, [
            'parent_id' => $child->id,
        ]);
    })->toThrow(ValidationException::class);
});

it('saves provider-managed areas with parents when only toggling active state', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();

    $parent = AddressArea::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'type' => 'state',
        'level' => 1,
        'name' => 'Provider Parent',
        'slug' => 'provider-parent',
        'source' => 'test-provider',
        'source_id' => 'parent',
    ]);

    $child = AddressArea::query()->create([
        'country_id' => $country->getKey(),
        'parent_id' => $parent->getKey(),
        'country_code' => 'MY',
        'type' => 'district',
        'level' => 2,
        'name' => 'Provider Child',
        'slug' => 'provider-child',
        'source' => 'test-provider',
        'source_id' => 'child',
    ]);

    AddressAreaRelationship::query()->create([
        'parent_address_area_id' => $parent->getKey(),
        'child_address_area_id' => $child->getKey(),
        'relationship_type' => 'contains',
        'hierarchy_type' => 'administrative',
        'source' => 'test-provider',
    ]);

    Livewire::test(EditAddressArea::class, ['record' => $child->getRouteKey()])
        ->fillForm(['is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($child->fresh()->is_active)->toBeFalse();
});

it('still requires an explicit hierarchy type for manual areas with parents', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();

    $parent = AddressArea::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'type' => 'state',
        'level' => 1,
        'name' => 'Manual Parent',
        'slug' => 'manual-parent',
        'source' => 'manual',
        'source_id' => 'parent',
    ]);

    $child = AddressArea::query()->create([
        'country_id' => $country->getKey(),
        'parent_id' => $parent->getKey(),
        'country_code' => 'MY',
        'type' => 'district',
        'level' => 2,
        'name' => 'Manual Child',
        'slug' => 'manual-child',
        'source' => 'manual',
        'source_id' => 'child',
    ]);

    Livewire::test(EditAddressArea::class, ['record' => $child->getRouteKey()])
        ->fillForm(['is_active' => false])
        ->call('save')
        ->assertHasFormErrors(['hierarchy_type']);

    Livewire::test(EditAddressArea::class, ['record' => $child->getRouteKey()])
        ->fillForm(['is_active' => false, 'hierarchy_type' => 'administrative'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($child->fresh()->is_active)->toBeFalse();
});

it('preselects the single existing hierarchy when editing areas', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();

    $parent = AddressArea::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'type' => 'state',
        'level' => 1,
        'name' => 'Preselect Parent',
        'slug' => 'preselect-parent',
        'source' => 'test-provider',
        'source_id' => 'parent',
    ]);

    $child = AddressArea::query()->create([
        'country_id' => $country->getKey(),
        'parent_id' => $parent->getKey(),
        'country_code' => 'MY',
        'type' => 'district',
        'level' => 2,
        'name' => 'Preselect Child',
        'slug' => 'preselect-child',
        'source' => 'test-provider',
        'source_id' => 'child',
    ]);

    AddressAreaRelationship::query()->create([
        'parent_address_area_id' => $parent->getKey(),
        'child_address_area_id' => $child->getKey(),
        'relationship_type' => 'contains',
        'hierarchy_type' => 'administrative',
        'source' => 'test-provider',
    ]);

    $test = Livewire::test(EditAddressArea::class, ['record' => $child->getRouteKey()]);

    expect($test->get('data.hierarchy_type'))->toBe('administrative');
});

it('preselects the sole configured hierarchy for areas without links', function (): void {
    config()->set('addressing.geography.providers', [SingleHierarchyTestProfile::class]);
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();

    $parent = AddressArea::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'type' => 'state',
        'level' => 1,
        'name' => 'Solo Parent',
        'slug' => 'solo-parent',
        'source' => 'manual',
        'source_id' => 'parent',
    ]);

    $child = AddressArea::query()->create([
        'country_id' => $country->getKey(),
        'parent_id' => $parent->getKey(),
        'country_code' => 'MY',
        'type' => 'district',
        'level' => 2,
        'name' => 'Solo Child',
        'slug' => 'solo-child',
        'source' => 'manual',
        'source_id' => 'child',
    ]);

    $test = Livewire::test(EditAddressArea::class, ['record' => $child->getRouteKey()]);

    expect($test->get('data.hierarchy_type'))->toBe('solo');
});

it('leaves hierarchy empty when several hierarchies apply', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();

    $parent = AddressArea::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'type' => 'state',
        'level' => 1,
        'name' => 'Ambiguous Parent',
        'slug' => 'ambiguous-parent',
        'source' => 'test-provider',
        'source_id' => 'parent',
    ]);

    $child = AddressArea::query()->create([
        'country_id' => $country->getKey(),
        'parent_id' => $parent->getKey(),
        'country_code' => 'MY',
        'type' => 'district',
        'level' => 2,
        'name' => 'Ambiguous Child',
        'slug' => 'ambiguous-child',
        'source' => 'test-provider',
        'source_id' => 'child',
    ]);

    foreach (['administrative', 'postal'] as $hierarchyType) {
        AddressAreaRelationship::query()->create([
            'parent_address_area_id' => $parent->getKey(),
            'child_address_area_id' => $child->getKey(),
            'relationship_type' => 'contains',
            'hierarchy_type' => $hierarchyType,
            'source' => 'test-provider',
        ]);
    }

    $test = Livewire::test(EditAddressArea::class, ['record' => $child->getRouteKey()]);

    expect($test->get('data.hierarchy_type'))->toBeNull();
});

final class SingleHierarchyTestProfile implements CountryAddressProfile
{
    public function countryCode(): string
    {
        return 'MY';
    }

    public function addressHierarchies(): array
    {
        return [
            new AddressHierarchyDefinition('solo', 'Solo', [
                new AddressLevelDefinition(
                    key: 'district',
                    label: 'Solo district',
                    kind: 'area',
                    hierarchyType: 'solo',
                    areaTypes: ['district'],
                    areaLevel: 2,
                ),
            ]),
        ];
    }
}
