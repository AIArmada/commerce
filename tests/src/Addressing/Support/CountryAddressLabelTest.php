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

    $makeState = static fn (string $name, string $code): State => State::query()->create([
        'country_id' => $country->id,
        'name' => $name,
        'label' => $name,
        'code' => $code,
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

    $this->johor = $makeState('Johor', '01');
    $johorArea = $makeArea('state', 1, 'Johor');
    AddressAreaStateLink::query()->create(['address_area_id' => $johorArea->id, 'state_id' => $this->johor->id]);
    $this->batuPahat = $makeArea('district', 2, 'Batu Pahat');
    $link($johorArea, $this->batuPahat);
    $paritSulong = $makeArea('mukim', 3, 'Parit Sulong');
    $link($this->batuPahat, $paritSulong);
    $link($johorArea, $paritSulong);
    $link($johorArea, $makeArea('bandar', 3, 'Bandar Penggaram'));

    $this->kelantan = $makeState('Kelantan', '03');
    $kelantanArea = $makeArea('state', 1, 'Kelantan');
    AddressAreaStateLink::query()->create(['address_area_id' => $kelantanArea->id, 'state_id' => $this->kelantan->id]);
    $link($kelantanArea, $makeArea('district', 2, 'Kota Bharu'));
    $link($kelantanArea, $makeArea('minor_district', 2, 'Lojing'));

    $this->putrajaya = $makeState('WP Putrajaya', '16');
    $putrajayaArea = $makeArea('wilayah_persekutuan', 1, 'WP Putrajaya');
    AddressAreaStateLink::query()->create([
        'address_area_id' => $putrajayaArea->id,
        'state_id' => $this->putrajaya->id,
        'hierarchy_type' => 'postal',
    ]);
    $link($putrajayaArea, $makeArea('precinct', 2, 'Precinct 9'), 'postal');
});

it('labels a single-type district scope precisely', function (): void {
    expect(app(CountryAddressProfileResolver::class)->levelLabel('MY', 'administrative_district', (string) $this->johor->id))
        ->toBe('District');
});

it('labels Kelantan districts with Jajahan overrides', function (): void {
    expect(app(CountryAddressProfileResolver::class)->levelLabel('MY', 'administrative_district', (string) $this->kelantan->id))
        ->toBe('Jajahan / Jajahan Kecil');
});

it('labels a precinct-only locality scope precisely', function (): void {
    expect(app(CountryAddressProfileResolver::class)->levelLabel('MY', 'postal_locality', (string) $this->putrajaya->id))
        ->toBe('Precinct');
});

it('joins mixed subdivision types in level order', function (): void {
    expect(app(CountryAddressProfileResolver::class)->levelLabel('MY', 'administrative_subdivision', (string) $this->johor->id))
        ->toBe('Mukim / Bandar');
});

it('narrows the label with the selected parent scope', function (): void {
    expect(app(CountryAddressProfileResolver::class)->levelLabel('MY', 'administrative_subdivision', (string) $this->johor->id, [
        'administrative_district' => (string) $this->batuPahat->id,
    ]))->toBe('Mukim');
});

it('falls back to the static label without scope and null when unknown', function (): void {
    $resolver = app(CountryAddressProfileResolver::class);

    expect($resolver->levelLabel('MY', 'administrative_district'))->toBe('District / Jajahan / Jajahan Kecil')
        ->and($resolver->levelLabel('MY', 'nope'))->toBeNull()
        ->and($resolver->levelLabel('XX', 'administrative_district'))->toBeNull();
});
