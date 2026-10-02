<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressHierarchyDefinition;
use AIArmada\Addressing\Data\AddressLevelDefinition;
use AIArmada\Addressing\Geography\Malaysia\MalaysiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;
use AIArmada\Addressing\Support\CountryAddressProfileResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seedCountry('MY');
    $this->seedCountry('ID');

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

describe('request-scoped type lookups', function (): void {
    beforeEach(function (): void {
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->typeLookupCount = static fn (): int => count(array_filter(
            DB::getQueryLog(),
            static fn (array $query): bool => preg_match('/select distinct ["`]?type["`]? from/i', $query['query']) === 1,
        ));
    });

    afterEach(function (): void {
        DB::disableQueryLog();
        DB::flushQueryLog();
    });

    it('reuses the type lookup when rendering the same scoped label repeatedly', function (): void {
        $resolver = app(CountryAddressProfileResolver::class);
        $stateId = (string) $this->johor->id;

        expect($resolver->levelLabel('MY', 'administrative_subdivision', $stateId))->toBe('Mukim / Bandar');
        expect($resolver->levelLabel('MY', 'administrative_subdivision', $stateId))->toBe('Mukim / Bandar');
        expect(($this->typeLookupCount)())->toBe(1);
    });

    it('keeps adaptive labels when changing parent scope and returning', function (): void {
        $resolver = app(CountryAddressProfileResolver::class);
        $stateId = (string) $this->johor->id;

        expect($resolver->levelLabel('MY', 'administrative_subdivision', $stateId))->toBe('Mukim / Bandar');
        expect($resolver->levelLabel('MY', 'administrative_subdivision', $stateId, [
            'administrative_district' => (string) $this->batuPahat->id,
        ]))->toBe('Mukim');
        expect($resolver->levelLabel('MY', 'administrative_subdivision', $stateId))->toBe('Mukim / Bandar');
        expect(($this->typeLookupCount)())->toBe(2);
    });

    it('isolates roles sharing the same parent area', function (): void {
        $resolver = app(CountryAddressProfileResolver::class);
        $stateId = (string) $this->johor->id;

        expect($resolver->levelLabel('MY', 'administrative_district', $stateId))->toBe('District');
        expect($resolver->levelLabel('MY', 'administrative_subdivision', $stateId))->toBe('Mukim / Bandar');
        expect($resolver->levelLabel('MY', 'administrative_district', $stateId))->toBe('District');
        expect(($this->typeLookupCount)())->toBe(2);
    });

    it('caches empty scopes while preserving the fallback label', function (): void {
        $resolver = app(CountryAddressProfileResolver::class);
        $stateId = (string) $this->johor->id;

        expect($resolver->levelLabel('MY', 'administrative_division', $stateId))->toBe('Division / Bahagian');
        expect($resolver->levelLabel('MY', 'administrative_division', $stateId))->toBe('Division / Bahagian');
        expect(($this->typeLookupCount)())->toBe(1);
    });

    it('refreshes the type set on a new request even when reusing the resolver', function (): void {
        $resolver = app(CountryAddressProfileResolver::class);
        $stateId = (string) $this->johor->id;
        $originalRequest = request();

        expect($resolver->levelLabel('MY', 'administrative_subdivision', $stateId))->toBe('Mukim / Bandar');
        AddressArea::query()->where('type', 'bandar')->update(['is_active' => false]);
        expect($resolver->levelLabel('MY', 'administrative_subdivision', $stateId))->toBe('Mukim / Bandar');

        app()->instance('request', Request::create('/'));

        try {
            expect($resolver->levelLabel('MY', 'administrative_subdivision', $stateId))->toBe('Mukim');
            expect(($this->typeLookupCount)())->toBe(2);
        } finally {
            app()->instance('request', $originalRequest);
        }
    });

    it('queries each scoped label when no request is bound', function (): void {
        $resolver = app(CountryAddressProfileResolver::class);
        $stateId = (string) $this->johor->id;
        $originalRequest = request();
        app()->offsetUnset('request');

        try {
            expect($resolver->levelLabel('MY', 'administrative_subdivision', $stateId))->toBe('Mukim / Bandar');
            expect($resolver->levelLabel('MY', 'administrative_subdivision', $stateId))->toBe('Mukim / Bandar');
            expect(($this->typeLookupCount)())->toBe(2);
        } finally {
            app()->instance('request', $originalRequest);
        }
    });

    it('isolates type filters, declared order, levels and hierarchy under one parent', function (): void {
        $provider = new class extends MalaysiaGeographyProvider
        {
            public function addressHierarchies(): array
            {
                return [new AddressHierarchyDefinition(
                    key: 'administrative',
                    label: 'Administrative',
                    levels: [
                        new AddressLevelDefinition(key: 'region', label: 'State', kind: 'state'),
                        new AddressLevelDefinition(key: 'mixed', label: 'Mixed fallback', kind: 'area', areaTypes: ['mukim', 'bandar'], areaLevels: [3], parentKey: 'region'),
                        new AddressLevelDefinition(key: 'filtered', label: 'Filtered fallback', kind: 'area', areaTypes: ['mukim'], areaLevels: [3], parentKey: 'region'),
                        new AddressLevelDefinition(key: 'reversed', label: 'Reversed fallback', kind: 'area', areaTypes: ['bandar', 'mukim'], areaLevels: [3], parentKey: 'region'),
                        new AddressLevelDefinition(key: 'level', label: 'Level fallback', kind: 'area', areaTypes: ['mukim', 'bandar'], areaLevels: [4], parentKey: 'region'),
                        new AddressLevelDefinition(key: 'postal', label: 'Postal fallback', kind: 'area', hierarchyType: 'postal', areaTypes: ['mukim', 'bandar'], areaLevels: [3], parentKey: 'region'),
                    ],
                )];
            }
        };
        app()->instance($provider::class, $provider);
        config()->set('addressing.geography.providers', [$provider::class]);
        $resolver = app(CountryAddressProfileResolver::class);
        $stateId = (string) $this->johor->id;

        expect($resolver->levelLabel('MY', 'administrative_mixed', $stateId))->toBe('Mukim / Bandar');
        expect($resolver->levelLabel('MY', 'administrative_filtered', $stateId))->toBe('Mukim');
        expect($resolver->levelLabel('MY', 'administrative_reversed', $stateId))->toBe('Bandar / Mukim');
        expect($resolver->levelLabel('MY', 'administrative_level', $stateId))->toBe('Level fallback');
        expect($resolver->levelLabel('MY', 'administrative_postal', $stateId))->toBe('Postal fallback');
        expect($resolver->levelLabel('MY', 'administrative_mixed', $stateId))->toBe('Mukim / Bandar');
        expect(($this->typeLookupCount)())->toBe(5);
    });
});

it('labels an Indonesian kota with its proper term', function (): void {
    $country = AddressCountry::query()->where('iso2', 'ID')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'name' => 'DKI Jakarta',
        'label' => 'DKI Jakarta',
        'code' => 'JK',
    ]);
    $provinceArea = AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'ID',
        'type' => 'province',
        'level' => 1,
        'name' => 'DKI Jakarta',
        'slug' => 'dki-jakarta',
        'source' => 'test',
        'source_id' => Str::uuid()->toString(),
    ]);
    AddressAreaStateLink::query()->create(['address_area_id' => $provinceArea->id, 'state_id' => $state->id]);
    $kota = AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'ID',
        'type' => 'city',
        'level' => 2,
        'name' => 'Jakarta Selatan',
        'slug' => 'jakarta-selatan',
        'source' => 'test',
        'source_id' => Str::uuid()->toString(),
    ]);
    AddressAreaRelationship::query()->create([
        'parent_address_area_id' => $provinceArea->id,
        'child_address_area_id' => $kota->id,
        'relationship_type' => 'contains',
        'hierarchy_type' => 'administrative',
    ]);

    expect(app(CountryAddressProfileResolver::class)->levelLabel('ID', 'regency', (string) $state->id))->toBe('Kota');
});

it('falls back to the static label without scope and null when unknown', function (): void {
    $resolver = app(CountryAddressProfileResolver::class);

    expect($resolver->levelLabel('MY', 'administrative_district'))->toBe('District / Jajahan / Jajahan Kecil')
        ->and($resolver->levelLabel('MY', 'nope'))->toBeNull()
        ->and($resolver->levelLabel('XX', 'administrative_district'))->toBeNull();
});

it('labels Indonesian types with national and special-autonomy terms', function (): void {
    $country = AddressCountry::query()->where('iso2', 'ID')->firstOrFail();

    $makeState = static fn (string $name, string $code): State => State::query()->create([
        'country_id' => $country->id,
        'name' => $name,
        'label' => $name,
        'code' => $code,
    ]);

    $makeArea = static fn (string $type, int $level, string $name): AddressArea => AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'ID',
        'type' => $type,
        'level' => $level,
        'name' => $name,
        'slug' => Str::slug($name),
        'source' => 'test',
        'source_id' => Str::uuid()->toString(),
    ]);

    $link = static function (AddressArea $parent, AddressArea $child): void {
        AddressAreaRelationship::query()->create([
            'parent_address_area_id' => $parent->id,
            'child_address_area_id' => $child->id,
            'relationship_type' => 'contains',
            'hierarchy_type' => 'administrative',
        ]);
    };

    // Builds province → regency → district → village (+urban) and returns
    // [stateId, regencyId, districtId].
    $chain = static function (string $stateName, string $stateCode, bool $withUrban = true) use ($makeState, $makeArea, $link): array {
        $state = $makeState($stateName, $stateCode);
        $province = $makeArea('province', 1, $stateName);
        AddressAreaStateLink::query()->create(['address_area_id' => $province->id, 'state_id' => $state->id]);
        $regency = $makeArea('regency', 2, $stateName . ' Regency');
        $link($province, $regency);
        $district = $makeArea('district', 3, $stateName . ' District');
        $link($regency, $district);
        $link($district, $makeArea('village', 4, $stateName . ' Village'));

        if ($withUrban) {
            $link($district, $makeArea('urban_village', 4, $stateName . ' Urban'));
        }

        return [(string) $state->id, (string) $regency->id, (string) $district->id];
    };

    $resolver = app(CountryAddressProfileResolver::class);

    [$jbState, $jbRegency, $jbDistrict] = $chain('Jawa Barat', 'JB');
    [$sbState, , $sbDistrict] = $chain('Sumatera Barat', 'SB', false);
    [$acState, , $acDistrict] = $chain('Aceh', 'AC');
    [$paState, $paRegency, $paDistrict] = $chain('Papua', 'PA', false);
    [$yoState, $yoRegency, $yoDistrict] = $chain('DI Yogyakarta', 'YO', false);

    expect($resolver->levelLabel('ID', 'district', $jbState, ['regency' => $jbRegency]))->toBe('Kecamatan')
        ->and($resolver->levelLabel('ID', 'village', $jbState, ['district' => $jbDistrict]))->toBe('Desa / Kelurahan')
        ->and($resolver->levelLabel('ID', 'village', $sbState, ['district' => $sbDistrict]))->toBe('Nagari')
        ->and($resolver->levelLabel('ID', 'village', $acState, ['district' => $acDistrict]))->toBe('Gampong')
        ->and($resolver->levelLabel('ID', 'district', $paState, ['regency' => $paRegency]))->toBe('Distrik')
        ->and($resolver->levelLabel('ID', 'village', $paState, ['district' => $paDistrict]))->toBe('Kampung')
        ->and($resolver->levelLabel('ID', 'district', $yoState, ['regency' => $yoRegency]))->toBe('Kapanewon / Kemantren')
        ->and($resolver->levelLabel('ID', 'village', $yoState, ['district' => $yoDistrict]))->toBe('Kalurahan');
});

it('falls back to Indonesian level labels without a state scope', function (): void {
    $resolver = app(CountryAddressProfileResolver::class);

    expect($resolver->levelLabel('ID', 'district'))->toBe('Kecamatan')
        ->and($resolver->levelLabel('ID', 'village'))->toBe('Desa / Kelurahan');
});
