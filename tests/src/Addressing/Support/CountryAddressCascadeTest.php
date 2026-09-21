<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;
use AIArmada\Addressing\Support\CountryAddressProfileResolver;
use Illuminate\Support\Str;

beforeEach(function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();

    $makeState = static fn (string $name): State => State::query()->create([
        'country_id' => $country->id,
        'name' => $name,
        'label' => $name,
    ]);

    $makeArea = static fn (string $type, int $level, string $name): AddressArea => AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'type' => $type,
        'level' => $level,
        'name' => $name,
        'slug' => Str::slug($name),
        'source' => 'test',
        'source_id' => Str::uuid()->toString(),
    ]);

    $link = static function (AddressArea $parent, AddressArea $child, string $hierarchyType = 'administrative'): void {
        AddressAreaRelationship::query()->create([
            'parent_address_area_id' => $parent->id,
            'child_address_area_id' => $child->id,
            'relationship_type' => 'contains',
            'hierarchy_type' => $hierarchyType,
        ]);
    };

    // Johor: districts own mukim rows.
    $this->johor = $makeState('Johor');
    $this->johorArea = $makeArea('state', 1, 'Johor');
    AddressAreaStateLink::query()->create(['address_area_id' => $this->johorArea->id, 'state_id' => $this->johor->id]);
    $this->batuPahat = $makeArea('district', 2, 'Batu Pahat');
    $link($this->johorArea, $this->batuPahat);
    $this->paritSulong = $makeArea('mukim', 3, 'Parit Sulong');
    $link($this->batuPahat, $this->paritSulong);
    $semerah = $makeArea('locality', 3, 'Semerah');
    $link($this->batuPahat, $semerah, 'postal');
    $link($this->johorArea, $semerah, 'postal');

    // Kuala Lumpur: mukim rows hang off the state, no districts exist.
    $this->kualaLumpur = $makeState('WP Kuala Lumpur');
    $this->kualaLumpurArea = $makeArea('wilayah_persekutuan', 1, 'WP Kuala Lumpur');
    AddressAreaStateLink::query()->create(['address_area_id' => $this->kualaLumpurArea->id, 'state_id' => $this->kualaLumpur->id]);
    $this->klMukim = $makeArea('mukim', 2, 'Mukim Kuala Lumpur');
    $link($this->kualaLumpurArea, $this->klMukim);
});

it('orders assignment roles with the primary hierarchy first', function (): void {
    expect(app(CountryAddressProfileResolver::class)->assignmentRoles('MY'))->toBe([
        'administrative_division',
        'administrative_district',
        'administrative_subdivision',
        'postal_locality',
    ]);
});

it('resolves hierarchies by key with an area-hierarchy fallback', function (): void {
    $resolver = app(CountryAddressProfileResolver::class);

    expect($resolver->hierarchy('MY', 'administrative')?->key)->toBe('administrative')
        ->and($resolver->hierarchy('MY', 'missing'))->toBeNull()
        ->and($resolver->firstAreaHierarchy('MY')?->key)->toBe('administrative')
        ->and($resolver->firstAreaHierarchy('XX'))->toBeNull();
});

it('resolves declared parent levels within the role hierarchy', function (): void {
    $resolver = app(CountryAddressProfileResolver::class);

    expect($resolver->parentLevel('MY', 'administrative_subdivision')?->key)->toBe('region')
        ->and($resolver->parentLevel('MY', 'administrative_subdivision')?->kind)->toBe('state')
        ->and($resolver->parentLevel('MY', 'postal_locality')?->key)->toBe('region')
        ->and($resolver->parentLevel('MY', 'nope'))->toBeNull();
});

it('narrows region-parented roles to a selected district with link proof', function (): void {
    $resolver = app(CountryAddressProfileResolver::class);

    expect($resolver->parentAreaIdForRole('MY', 'administrative_subdivision', (string) $this->johor->id, [
        'administrative_district' => (string) $this->batuPahat->id,
    ]))->toBe((string) $this->batuPahat->id);
});

it('falls back to the state parent when no narrowing selection applies', function (): void {
    $resolver = app(CountryAddressProfileResolver::class);

    expect($resolver->parentAreaIdForRole('MY', 'administrative_subdivision', (string) $this->johor->id, []))
        ->toBe((string) $this->johorArea->id)
        ->and($resolver->parentAreaIdForRole('MY', 'administrative_subdivision', (string) $this->kualaLumpur->id, []))
        ->toBe((string) $this->kualaLumpurArea->id)
        ->and($resolver->parentAreaIdForRole('MY', 'administrative_district', (string) $this->johor->id, []))
        ->toBe((string) $this->johorArea->id)
        ->and($resolver->parentAreaIdForRole('MY', 'nope', (string) $this->johor->id, []))->toBeNull();
});

it('resets narrowed successors with their narrowing role', function (): void {
    $resolver = app(CountryAddressProfileResolver::class);

    expect($resolver->successorRoles('MY', 'administrative_district'))->toBe(['administrative_subdivision', 'postal_locality'])
        ->and($resolver->successorRoles('MY', 'postal_locality'))->toBe([])
        ->and($resolver->successorRoles('MY', 'nope'))->toBe([])
        ->and($resolver->stateDependentRoles('MY'))->toBe([
            'administrative_division',
            'administrative_district',
            'administrative_subdivision',
            'postal_locality',
        ]);
});

it('narrows localities to a picked district across hierarchies', function (): void {
    $resolver = app(CountryAddressProfileResolver::class);

    expect($resolver->parentAreaIdForRole('MY', 'postal_locality', (string) $this->johor->id, [
        'administrative_district' => (string) $this->batuPahat->id,
    ]))->toBe((string) $this->batuPahat->id)
        ->and($resolver->parentAreaIdForRole('MY', 'postal_locality', (string) $this->johor->id, []))
        ->toBe((string) $this->johorArea->id);
});

it('gates localities on the district only where links prove the structure', function (): void {
    $resolver = app(CountryAddressProfileResolver::class);

    expect($resolver->effectiveParentLevel('MY', 'postal_locality', (string) $this->johor->id)?->key)
        ->toBe('district')
        ->and($resolver->effectiveParentLevel('MY', 'postal_locality', (string) $this->kualaLumpur->id)?->key)
        ->toBe('region');
});

it('gates subdivisions on the district only where links prove the structure', function (): void {
    $resolver = app(CountryAddressProfileResolver::class);

    expect($resolver->effectiveParentLevel('MY', 'administrative_subdivision', (string) $this->johor->id)?->key)
        ->toBe('district')
        ->and($resolver->effectiveParentLevel('MY', 'administrative_subdivision', (string) $this->kualaLumpur->id)?->key)
        ->toBe('region')
        ->and($resolver->effectiveParentLevel('MY', 'administrative_subdivision', null)?->key)
        ->toBe('region')
        ->and($resolver->effectiveParentLevel('MY', 'administrative_district', (string) $this->johor->id)?->key)
        ->toBe('region')
        ->and($resolver->effectiveParentLevel('MY', 'nope', (string) $this->johor->id))->toBeNull();
});
