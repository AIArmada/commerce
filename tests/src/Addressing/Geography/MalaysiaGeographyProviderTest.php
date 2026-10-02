<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Contracts\AddressAreaSource;
use AIArmada\Addressing\Contracts\CountryAddressAreaMetadataProvider;
use AIArmada\Addressing\Contracts\CountryGeographyProvider;
use AIArmada\Addressing\Contracts\CountryHierarchyProvider;
use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Data\PostalCodeData;
use AIArmada\Addressing\Geography\Malaysia\MalaysiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRole;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;
use AIArmada\Addressing\Support\ArrayAddressAreaSource;
use AIArmada\Addressing\Support\CsvPostalCodeSource;
use AIArmada\Addressing\Support\ModelResolver;
use Illuminate\Support\Str;

final class ObsoleteLinkFakeGeographyProvider implements CountryAddressAreaMetadataProvider, CountryGeographyProvider, CountryHierarchyProvider
{
    public function providerKey(): string
    {
        return 'test.obsolete-links';
    }

    public function countryCode(): string
    {
        return 'MY';
    }

    public function seed(AddressCountry $country): void
    {
        ModelResolver::stateClass()::query()->updateOrCreate(
            ['country_id' => $country->getKey(), 'code' => 'T1'],
            ['name' => 'Test State', 'country_code' => 'MY'],
        );
    }

    public function addressHierarchies(): array
    {
        return [];
    }

    public function addressAreaSource(): AddressAreaSource
    {
        return new ArrayAddressAreaSource('fake-areas', [
            new AddressAreaData(
                source: 'fake-areas',
                sourceId: 'r1',
                countryCode: 'MY',
                type: 'state',
                level: 1,
                name: 'Test Region',
                code: 'R1',
            ),
        ]);
    }

    public function stateAreaMappings(): array
    {
        return [
            'T1' => ['area_code' => 'R1', 'source' => 'fake-areas', 'area_level' => 1],
        ];
    }

    public function areaRoles(AddressCountry $country): array
    {
        return [];
    }

    public function areaNames(AddressCountry $country): array
    {
        return [];
    }

    public function areaRelationships(AddressCountry $country): array
    {
        return [];
    }
}

it('preserves globally seeded states and adds missing Malaysian states', function (): void {
    $country = $this->seedCountry('MY');
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '14',
        'name' => 'Kuala Lumpur',
        'label' => 'Kuala Lumpur',
    ]);

    app(MalaysiaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('WP Kuala Lumpur')
        ->and(State::query()->where('country_id', $country->id)->count())->toBeGreaterThan(1);
});

it('removes obsolete provider-owned state links when reseeding', function (): void {
    $this->seedCountry('MY');
    config()->set('addressing.geography.providers', [ObsoleteLinkFakeGeographyProvider::class]);

    app(SeedCountryGeographiesAction::class)->execute('MY');

    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $state = State::query()->where('country_id', $country->id)->where('code', 'T1')->firstOrFail();
    $oldArea = AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'type' => 'state',
        'level' => 1,
        'name' => 'Old Region',
        'slug' => 'old-region',
        'source' => 'stale-feed',
        'source_id' => Str::uuid()->toString(),
    ]);
    $oldLink = AddressAreaStateLink::query()->create([
        'address_area_id' => $oldArea->id,
        'state_id' => $state->id,
        'metadata' => ['provider' => 'test.obsolete-links'],
    ]);

    app(SeedCountryGeographiesAction::class)->execute('MY');

    expect(AddressAreaStateLink::query()->whereKey($oldLink->id)->exists())->toBeFalse()
        ->and(AddressAreaStateLink::query()->where('metadata->provider', 'test.obsolete-links')->count())->toBe(1);
});

it('deactivates prior areas when a provider changes its imported source key', function (): void {
    $this->seedCountry('MY');

    $provider = new class implements CountryAddressAreaMetadataProvider, CountryGeographyProvider, CountryHierarchyProvider
    {
        public function providerKey(): string
        {
            return 'test.addressing.rekey';
        }

        public function countryCode(): string
        {
            return 'MY';
        }

        public function seed(AddressCountry $country): void {}

        public function addressHierarchies(): array
        {
            return [];
        }

        public function addressAreaSource(): AddressAreaSource
        {
            $source = (string) config('addressing.test_provider_source');

            return new ArrayAddressAreaSource($source, [
                new AddressAreaData(
                    source: $source,
                    sourceId: 'region',
                    countryCode: 'MY',
                    type: 'state',
                    level: 1,
                    name: 'Test Region',
                ),
            ]);
        }

        public function stateAreaMappings(): array
        {
            return [];
        }

        public function areaRoles(AddressCountry $country): array
        {
            return [
                'region' => [['role' => 'region', 'country_code' => 'MY', 'is_primary' => true]],
            ];
        }

        public function areaNames(AddressCountry $country): array
        {
            return [];
        }

        public function areaRelationships(AddressCountry $country): array
        {
            return [];
        }
    };
    config()->set('addressing.geography.providers', [get_class($provider)]);
    config()->set('addressing.test_provider_source', 'test-feed-v1');

    app(SeedCountryGeographiesAction::class)->execute('MY');
    $firstArea = AddressArea::query()->where('source', 'test-feed-v1')->firstOrFail();

    config()->set('addressing.test_provider_source', 'test-feed-v2');
    app(SeedCountryGeographiesAction::class)->execute('MY');

    expect($firstArea->refresh()->is_active)->toBeFalse()
        ->and(AddressArea::query()->where('source', 'test-feed-v2')->value('is_active'))->toBeTrue()
        ->and($firstArea->metadata['provider'])->toBe('test.addressing.rekey')
        ->and(AddressAreaRole::query()
            ->where('source', 'test.addressing.rekey')
            ->value('address_area_id'))->toBe(AddressArea::query()->where('source', 'test-feed-v2')->value('id'));
});

it('exposes district-parented postal localities under the region', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);

    $locality = collect(collect($provider->addressHierarchies())->firstWhere('key', 'postal')->levels)
        ->firstWhere('key', 'locality');

    expect($locality->areaLevels)->toContain(2, 3, 4);

    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;
    $paritSulong = $areas->get('my:subdistrict:district:johor:batu-pahat:parit-sulong');

    expect($paritSulong->type)->toBe('locality')
        ->and($paritSulong->level)->toBe(3)
        ->and($paritSulong->parentSourceId)->toBe('my:district:johor:batu-pahat')
        ->and($areas->get('my:subdistrict:district:johor:segamat:pekan-chaah')->parentSourceId)->toBe('my:district:johor:segamat');

    $country = new AddressCountry;
    $roles = $provider->areaRoles($country);

    expect($roles['my:subdistrict:district:johor:batu-pahat:parit-sulong'][0]['role'])->toBe('postal_locality');

    $links = $provider->areaRelationships($country)['my:subdistrict:district:johor:batu-pahat:parit-sulong'];

    expect($links)->toContain(
        ['parent_source_id' => 'my:district:johor:batu-pahat', 'relationship_type' => 'contains', 'hierarchy_type' => 'postal'],
        ['parent_source_id' => 'my:state:johor', 'relationship_type' => 'contains', 'hierarchy_type' => 'postal'],
    );
});

it('exposes the shared-postcode Batu Pahat towns as postal localities', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    $tongkangPechah = $areas->get('my:subdistrict:district:johor:batu-pahat:tongkang-pechah');
    $paritYaani = $areas->get('my:subdistrict:district:johor:batu-pahat:parit-yaani');

    expect($tongkangPechah->type)->toBe('locality')
        ->and($tongkangPechah->level)->toBe(3)
        ->and($tongkangPechah->parentSourceId)->toBe('my:district:johor:batu-pahat')
        ->and($paritYaani->type)->toBe('locality')
        ->and($paritYaani->level)->toBe(3)
        ->and($paritYaani->parentSourceId)->toBe('my:district:johor:batu-pahat')
        ->and($areas->get('my:subdistrict:district:johor:johor-bahru:skudai')->parentSourceId)->toBe('my:district:johor:johor-bahru')
        ->and($areas->get('my:subdistrict:district:johor:kulai:saleng')->parentSourceId)->toBe('my:district:johor:kulai')
        ->and($areas->get('my:subdistrict:district:johor:kulai:kelapa-sawit')->parentSourceId)->toBe('my:district:johor:kulai')
        ->and($areas->get('my:subdistrict:district:johor:kluang:chamek')->parentSourceId)->toBe('my:district:johor:kluang')
        ->and($areas->get('my:subdistrict:district:johor:tangkak:tanjung-agas')->parentSourceId)->toBe('my:district:johor:tangkak');

    $country = new AddressCountry;
    $roles = $provider->areaRoles($country);

    expect($roles['my:subdistrict:district:johor:batu-pahat:tongkang-pechah'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:district:johor:batu-pahat:parit-yaani'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:district:johor:johor-bahru:skudai'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:district:johor:kulai:saleng'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:district:johor:kulai:kelapa-sawit'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:district:johor:kluang:chamek'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:district:johor:tangkak:tanjung-agas'][0]['role'])->toBe('postal_locality');

    $links = $provider->areaRelationships($country)['my:subdistrict:district:johor:batu-pahat:parit-yaani'];

    expect($links)->toContain(
        ['parent_source_id' => 'my:district:johor:batu-pahat', 'relationship_type' => 'contains', 'hierarchy_type' => 'postal'],
        ['parent_source_id' => 'my:state:johor', 'relationship_type' => 'contains', 'hierarchy_type' => 'postal'],
    );
});

it('exposes the Melaka and Negeri Sembilan expansion towns', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    $lubokChina = $areas->get('my:subdistrict:district:melaka:alor-gajah:lubok-china');
    $telokKemang = $areas->get('my:subdistrict:district:negeri-sembilan:port-dickson:telok-kemang');

    expect($lubokChina->type)->toBe('locality')
        ->and($lubokChina->level)->toBe(3)
        ->and($lubokChina->parentSourceId)->toBe('my:district:melaka:alor-gajah')
        ->and($telokKemang->type)->toBe('locality')
        ->and($telokKemang->level)->toBe(3)
        ->and($telokKemang->parentSourceId)->toBe('my:district:negeri-sembilan:port-dickson');

    $country = new AddressCountry;
    $roles = $provider->areaRoles($country);

    expect($roles['my:subdistrict:district:melaka:alor-gajah:lubok-china'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:district:negeri-sembilan:port-dickson:telok-kemang'][0]['role'])->toBe('postal_locality');
});

it('exposes the Kedah, Perlis, and Penang expansion towns', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('my:subdistrict:district:kedah:kuala-muda:tikam-batu')->parentSourceId)->toBe('my:district:kedah:kuala-muda')
        ->and($areas->get('my:subdistrict:state:perlis:kangar')->parentSourceId)->toBe('my:state:perlis')
        ->and($areas->get('my:subdistrict:state:perlis:kangar')->level)->toBe(2)
        ->and($areas->get('my:subdistrict:state:perlis:padang-besar')->parentSourceId)->toBe('my:state:perlis')
        ->and($areas->get('my:subdistrict:state:perlis:kaki-bukit')->parentSourceId)->toBe('my:state:perlis')
        ->and($areas->get('my:subdistrict:state:perlis:simpang-empat')->parentSourceId)->toBe('my:state:perlis')
        ->and($areas->get('my:subdistrict:district:pulau-pinang:barat-daya:teluk-bahang')->parentSourceId)->toBe('my:district:pulau-pinang:barat-daya')
        ->and($areas->get('my:subdistrict:district:pulau-pinang:seberang-perai-selatan:batu-kawan')->parentSourceId)->toBe('my:district:pulau-pinang:seberang-perai-selatan')
        ->and($areas->get('my:subdistrict:district:pulau-pinang:seberang-perai-utara:bertam')->parentSourceId)->toBe('my:district:pulau-pinang:seberang-perai-utara');

    $country = new AddressCountry;
    $roles = $provider->areaRoles($country);

    expect($roles['my:subdistrict:district:kedah:kuala-muda:tikam-batu'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:state:perlis:kangar'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:state:perlis:padang-besar'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:state:perlis:kaki-bukit'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:state:perlis:simpang-empat'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:district:pulau-pinang:barat-daya:teluk-bahang'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:district:pulau-pinang:seberang-perai-selatan:batu-kawan'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:district:pulau-pinang:seberang-perai-utara:bertam'][0]['role'])->toBe('postal_locality');

    $links = $provider->areaRelationships($country)['my:subdistrict:state:perlis:kangar'];

    expect($links)->toBe([
        ['parent_source_id' => 'my:state:perlis', 'relationship_type' => 'contains', 'hierarchy_type' => 'postal'],
    ]);
});

it('exposes the Kelantan expansion town', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    $kokLanas = $areas->get('my:subdistrict:district:kelantan:kota-bharu:kok-lanas');

    expect($kokLanas->type)->toBe('locality')
        ->and($kokLanas->level)->toBe(3)
        ->and($kokLanas->parentSourceId)->toBe('my:district:kelantan:kota-bharu');

    $country = new AddressCountry;
    $roles = $provider->areaRoles($country);

    expect($roles['my:subdistrict:district:kelantan:kota-bharu:kok-lanas'][0]['role'])->toBe('postal_locality');
});

it('exposes the Pahang, Perak, and Selangor expansion towns', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('my:subdistrict:district:pahang:bentong:bukit-tinggi')->parentSourceId)->toBe('my:district:pahang:bentong')
        ->and($areas->get('my:subdistrict:district:pahang:bera:mengkarak')->parentSourceId)->toBe('my:district:pahang:bera')
        ->and($areas->get('my:subdistrict:district:perak:kerian:simpang-lima')->parentSourceId)->toBe('my:district:perak:kerian')
        ->and($areas->get('my:subdistrict:district:selangor:petaling:seri-kembangan')->parentSourceId)->toBe('my:district:selangor:petaling')
        ->and($areas->get('my:subdistrict:district:selangor:petaling:serdang')->parentSourceId)->toBe('my:district:selangor:petaling')
        ->and($areas->get('my:subdistrict:district:selangor:hulu-langat:balakong')->parentSourceId)->toBe('my:district:selangor:hulu-langat');

    $country = new AddressCountry;
    $roles = $provider->areaRoles($country);

    expect($roles['my:subdistrict:district:pahang:bentong:bukit-tinggi'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:district:pahang:bera:mengkarak'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:district:perak:kerian:simpang-lima'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:district:selangor:petaling:seri-kembangan'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:district:selangor:petaling:serdang'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:district:selangor:hulu-langat:balakong'][0]['role'])->toBe('postal_locality');
});

it('exposes the gazetted Ipoh (U) and Ipoh (S) bandars under Kinta', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    $utara = $areas->get('my:subdistrict:district:perak:kinta:ipoh-u');
    $selatan = $areas->get('my:subdistrict:district:perak:kinta:ipoh-s');

    expect($utara->name)->toBe('Ipoh (U)')
        ->and($utara->type)->toBe('bandar')
        ->and($utara->parentSourceId)->toBe('my:district:perak:kinta')
        ->and($selatan->name)->toBe('Ipoh (S)')
        ->and($selatan->type)->toBe('bandar')
        ->and($selatan->parentSourceId)->toBe('my:district:perak:kinta')
        ->and($areas->has('my:subdistrict:district:perak:kinta:ipoh-n'))->toBeFalse();
});

it('exposes Sabah daerah kecil as the administrative subdivision tier', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);

    $subdivision = collect(collect($provider->addressHierarchies())->firstWhere('key', 'administrative')->levels)
        ->firstWhere('key', 'subdivision');

    expect($subdivision->areaTypes)->toContain('daerah_kecil')
        ->and($provider->areaTypeLabels()['daerah_kecil'])->toBe('Daerah Kecil');

    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    foreach (['tuaran:tamparuli', 'kuala-penyu:menumbok', 'kudat:banggi', 'kudat:matunggong', 'tenom:kemabong', 'nabawan:pagalungan'] as $suffix) {
        $area = $areas->get('my:subdistrict:district:sabah:' . $suffix);

        expect($area->type)->toBe('daerah_kecil', $suffix)
            ->and($area->level)->toBe(4, $suffix);
    }

    expect($areas->get('my:subdistrict:district:sabah:tuaran:tamparuli')->parentSourceId)->toBe('my:district:sabah:tuaran');

    $roles = $provider->areaRoles(new AddressCountry);

    expect($roles['my:subdistrict:district:sabah:tuaran:tamparuli'][0]['role'])->toBe('administrative_subdivision')
        ->and($roles['my:subdistrict:district:sabah:kudat:banggi'][0]['role'])->toBe('administrative_subdivision');

    $links = $provider->areaRelationships(new AddressCountry)['my:subdistrict:district:sabah:tuaran:tamparuli'];

    expect($links)->toContain(
        ['parent_source_id' => 'my:district:sabah:tuaran', 'relationship_type' => 'contains', 'hierarchy_type' => 'administrative'],
        ['parent_source_id' => 'my:state:sabah', 'relationship_type' => 'contains', 'hierarchy_type' => 'administrative'],
    );
});

it('exposes the Sabah rectified towns as postal localities', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    $expected = [
        // [suffix, parent district]
        'tuaran:pekan-tamparuli' => 'tuaran',
        'kuala-penyu:pekan-menumbok' => 'kuala-penyu',
        'membakut:pekan-membakut' => 'membakut',
        'sook:pekan-sook' => 'sook',
        'paitan:pekan-paitan' => 'paitan',
        'paitan:pamol' => 'paitan',
        'kalabakan:pekan-kalabakan' => 'kalabakan',
        'kota-belud:pekan-nabalu' => 'kota-belud',
        'papar:pekan-lok-kawi' => 'papar',
        'kota-marudu:bandau' => 'kota-marudu',
        'kota-marudu:langkon' => 'kota-marudu',
        'kudat:pekan-matunggong' => 'kudat',
        'kudat:pekan-sikuati' => 'kudat',
        'kudat:pekan-karakit' => 'kudat',
        'tawau:merotai-besar' => 'tawau',
        'kota-kinabalu:kota-kinabalu' => 'kota-kinabalu',
        'penampang:pekan-donggongon' => 'penampang',
        'sipitang:sindumin' => 'sipitang',
        'sipitang:mesapol' => 'sipitang',
        'tenom:melalap' => 'tenom',
        'keningau:apin-apin' => 'keningau',
        'lahad-datu:tungku' => 'lahad-datu',
        'nabawan:sepulot' => 'nabawan',
        'tuaran:kiulu' => 'tuaran',
        'papar:kimanis' => 'papar',
        'papar:kinarut' => 'papar',
        'papar:benoni' => 'papar',
    ];

    foreach ($expected as $suffix => $district) {
        $area = $areas->get('my:subdistrict:district:sabah:' . $suffix);

        expect($area->type)->toBe('locality', $suffix)
            ->and($area->level)->toBe(4, $suffix)
            ->and($area->parentSourceId)->toBe('my:district:sabah:' . $district, $suffix);
    }

    $roles = $provider->areaRoles(new AddressCountry);

    expect($roles['my:subdistrict:district:sabah:membakut:pekan-membakut'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:district:sabah:paitan:pamol'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:district:sabah:tawau:merotai-besar'][0]['role'])->toBe('postal_locality');

    $links = $provider->areaRelationships(new AddressCountry)['my:subdistrict:district:sabah:membakut:pekan-membakut'];

    expect($links)->toContain(
        ['parent_source_id' => 'my:district:sabah:membakut', 'relationship_type' => 'contains', 'hierarchy_type' => 'postal'],
        ['parent_source_id' => 'my:state:sabah', 'relationship_type' => 'contains', 'hierarchy_type' => 'postal'],
    );
});

it('drops the Sabah non-town subdistrict rows without orphans', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    $removed = [
        'beaufort:lumadan', 'sipitang:lumadan', 'beaufort:klias', 'kuala-penyu:kerukan',
        'keningau:bingkor', 'keningau:liawan', 'tenom:tomani', 'sipitang:long-pasia',
        'beluran:klagan', 'beluran:kolapis', 'beluran:sapi', 'paitan:jambongan',
        'kinabatangan:sukau', 'kinabatangan:lamag', 'kinabatangan:paris', 'kinabatangan:pekan-kinabatangan',
        'sandakan:elopura', 'sandakan:tanjong-papat', 'sandakan:gum-gum', 'sandakan:sekong',
        'sandakan:sungai-manila', 'tongod:kuamut', 'kalabakan:luasong', 'kunak:madai',
        'lahad-datu:segama', 'lahad-datu:silabukan', 'semporna:bum-bum', 'semporna:bubul',
        'tawau:balung', 'tawau:merotai', 'tawau:apas', 'tawau:sri-tanjung',
        'sandakan:sandakan', 'tawau:tawau', 'lahad-datu:lahad-datu', 'semporna:semporna',
        'kunak:kunak', 'sipitang:sipitang', 'nabawan:nabawan', 'tenom:tenom',
        'tambunan:tambunan', 'keningau:keningau', 'kuala-penyu:kuala-penyu', 'beaufort:beaufort',
        'beluran:beluran', 'tuaran:tuaran', 'ranau:ranau', 'papar:papar',
        'penampang:penampang', 'kota-belud:kota-belud', 'kota-marudu:kota-marudu', 'kudat:kudat',
    ];

    foreach ($removed as $suffix) {
        expect($areas->has('my:subdistrict:district:sabah:' . $suffix))->toBeFalse($suffix);
    }

    expect($areas->has('my:subdistrict:district:sabah:beluran:pamol'))->toBeFalse();
});

it('places the Sabah rectified postcodes on exactly one primary area', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MY', $dir . '/malaysia-postal-codes.csv', $dir . '/malaysia-postal-code-areas.csv', 'aiarmada_addressing_malaysia_v1');

    $byCode = $source->postalCodes()->collect()->groupBy->code;

    // Phantom Kota Marudu overflow codes are gone entirely.
    foreach (['89130', '89137', '89138', '89139'] as $code) {
        expect($byCode->has($code))->toBeFalse($code);
    }

    $primaries = static fn (string $code): array => $byCode->get($code, collect())
        ->filter(static fn (PostalCodeData $row): bool => $row->isPrimary)
        ->map(static fn (PostalCodeData $row): string => (string) $row->areaSourceId)
        ->all();

    expect($primaries('89250'))->toBe(['my:subdistrict:district:sabah:tuaran:pekan-tamparuli'])
        ->and($primaries('89760'))->toBe(['my:subdistrict:district:sabah:kuala-penyu:pekan-menumbok'])
        ->and($primaries('89720'))->toBe(['my:subdistrict:district:sabah:membakut:pekan-membakut'])
        ->and($primaries('89500'))->toBe(['my:subdistrict:district:sabah:penampang:pekan-donggongon'])
        ->and($primaries('90400'))->toBe(['my:subdistrict:district:sabah:paitan:pamol'])
        ->and($primaries('91150'))->toBe(['my:subdistrict:district:sabah:lahad-datu:cenderawasih']);

    $secondaries = static fn (string $code): array => $byCode->get($code, collect())
        ->reject(static fn (PostalCodeData $row): bool => $row->isPrimary)
        ->map(static fn (PostalCodeData $row): string => (string) $row->areaSourceId)
        ->all();

    expect($secondaries('89050'))->toContain(
        'my:subdistrict:district:sabah:kudat:pekan-karakit',
        'my:subdistrict:district:sabah:kudat:pekan-sikuati',
        'my:subdistrict:district:sabah:kudat:pekan-matunggong',
        'my:subdistrict:district:sabah:kota-marudu:langkon',
        'my:subdistrict:district:sabah:kota-marudu:tandek',
    )->not->toContain(
        'my:subdistrict:district:sabah:kudat:banggi',
        'my:subdistrict:district:sabah:kudat:matunggong',
    );

    expect($secondaries('91150'))->toBeEmpty();
});

it('exposes Sarawak daerah kecil as the administrative subdivision tier', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    foreach (['betong:debak', 'betong:spaoh', 'kabong:roban', 'pusa:maludam', 'saratok:nanga-budu', 'lundu:sematan', 'lawas:sundar', 'lawas:trusan', 'limbang:nanga-medamit', 'telang-usan:long-lama', 'dalat:oya', 'mukah:balingian', 'lubok-antu:engkilili', 'asajaya:sadong-jaya', 'marudi:bario', 'subis:sibuti', 'subis:niah-suai'] as $suffix) {
        $area = $areas->get('my:subdistrict:district:sarawak:' . $suffix);

        expect($area->type)->toBe('daerah_kecil', $suffix)
            ->and($area->level)->toBe(4, $suffix);
    }

    expect($areas->get('my:subdistrict:district:sarawak:mukah:balingian')->parentSourceId)->toBe('my:district:sarawak:mukah')
        ->and($areas->get('my:subdistrict:district:sarawak:subis:niah-suai')->parentSourceId)->toBe('my:district:sarawak:subis');

    $roles = $provider->areaRoles(new AddressCountry);

    expect($roles['my:subdistrict:district:sarawak:betong:spaoh'][0]['role'])->toBe('administrative_subdivision')
        ->and($roles['my:subdistrict:district:sarawak:marudi:bario'][0]['role'])->toBe('administrative_subdivision');

    $links = $provider->areaRelationships(new AddressCountry)['my:subdistrict:district:sarawak:mukah:balingian'];

    expect($links)->toContain(
        ['parent_source_id' => 'my:district:sarawak:mukah', 'relationship_type' => 'contains', 'hierarchy_type' => 'administrative'],
        ['parent_source_id' => 'my:state:sarawak', 'relationship_type' => 'contains', 'hierarchy_type' => 'administrative'],
    );
});

it('exposes the Sarawak rectified towns as postal localities', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    $expected = [
        // [suffix, parent district]
        'betong:betong' => 'betong',
        'betong:pekan-debak' => 'betong',
        'betong:pekan-spaoh' => 'betong',
        'kabong:kabong' => 'kabong',
        'kabong:pekan-roban' => 'kabong',
        'pusa:pusa' => 'pusa',
        'saratok:saratok' => 'saratok',
        'bintulu:bintulu' => 'bintulu',
        'sebauh:sebauh' => 'sebauh',
        'tatau:tatau' => 'tatau',
        'belaga:belaga' => 'belaga',
        'kapit:kapit' => 'kapit',
        'song:song' => 'song',
        'bau:bau' => 'bau',
        'bau:siniawan' => 'bau',
        'kuching:kuching' => 'kuching',
        'lundu:lundu' => 'lundu',
        'lundu:pekan-sematan' => 'lundu',
        'lawas:lawas' => 'lawas',
        'lawas:pekan-sundar' => 'lawas',
        'lawas:pekan-trusan' => 'lawas',
        'limbang:limbang' => 'limbang',
        'limbang:pekan-nanga-medamit' => 'limbang',
        'beluru:beluru' => 'beluru',
        'marudi:marudi' => 'marudi',
        'marudi:pekan-bario' => 'marudi',
        'miri:miri' => 'miri',
        'miri:lutong' => 'miri',
        'subis:batu-niah' => 'subis',
        'subis:bekenu' => 'subis',
        'subis:niah' => 'subis',
        'telang-usan:pekan-long-lama' => 'telang-usan',
        'dalat:dalat' => 'dalat',
        'dalat:pekan-oya' => 'dalat',
        'daro:daro' => 'daro',
        'matu:matu' => 'matu',
        'mukah:mukah' => 'mukah',
        'mukah:pekan-balingian' => 'mukah',
        'tanjung-manis:belawai' => 'tanjung-manis',
        'asajaya:asajaya' => 'asajaya',
        'asajaya:pekan-sadong-jaya' => 'asajaya',
        'kota-samarahan:kota-samarahan' => 'kota-samarahan',
        'sebuyau:sebuyau' => 'sebuyau',
        'simunjan:simunjan' => 'simunjan',
        'julau:julau' => 'julau',
        'meradong:bintangor' => 'meradong',
        'pakan:pakan' => 'pakan',
        'sarikei:sarikei' => 'sarikei',
        'serian:serian' => 'serian',
        'siburan:siburan' => 'siburan',
        'tebedu:tebedu' => 'tebedu',
        'kanowit:kanowit' => 'kanowit',
        'selangau:selangau' => 'selangau',
        'sibu:sibu' => 'sibu',
        'sibu:sibu-jaya' => 'sibu',
        'lingga:lingga' => 'lingga',
        'lubok-antu:lubok-antu' => 'lubok-antu',
        'lubok-antu:pekan-engkilili' => 'lubok-antu',
        'pantu:pantu' => 'pantu',
        'sri-aman:sri-aman' => 'sri-aman',
    ];

    foreach ($expected as $suffix => $district) {
        $area = $areas->get('my:subdistrict:district:sarawak:' . $suffix);

        expect($area->type)->toBe('locality', $suffix)
            ->and($area->level)->toBe(4, $suffix)
            ->and($area->parentSourceId)->toBe('my:district:sarawak:' . $district, $suffix);
    }

    $roles = $provider->areaRoles(new AddressCountry);

    expect($roles['my:subdistrict:district:sarawak:pusa:pusa'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:district:sarawak:marudi:pekan-bario'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:district:sarawak:miri:lutong'][0]['role'])->toBe('postal_locality');

    $links = $provider->areaRelationships(new AddressCountry)['my:subdistrict:district:sarawak:pusa:pusa'];

    expect($links)->toContain(
        ['parent_source_id' => 'my:district:sarawak:pusa', 'relationship_type' => 'contains', 'hierarchy_type' => 'postal'],
        ['parent_source_id' => 'my:state:sarawak', 'relationship_type' => 'contains', 'hierarchy_type' => 'postal'],
    );
});

it('drops the Sarawak non-town subdistrict rows without orphans', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    $removed = [
        'betong:lidong', 'betong:padeh', 'kabong:nyabor', 'pusa:triso',
        'bintulu:jepak', 'bintulu:kemena', 'bintulu:kidurong', 'belaga:long-murum',
        'belaga:sungai-asap', 'kapit:nanga-merit', 'kapit:pelagus', 'song:katibas',
        'song:nanga-engkuah', 'bau:buso', 'bau:krokong', 'bau:musi',
        'bau:pangkalan-tebang', 'bau:tondong', 'kuching:batu-kawa', 'kuching:matang',
        'kuching:padawan', 'kuching:santubong', 'kuching:semariang', 'lundu:biawak',
        'lawas:bakelalan', 'lawas:long-semado', 'lawas:merapok', 'limbang:batu-danau',
        'limbang:kubong', 'beluru:lapok', 'beluru:long-jegan', 'marudi:long-teru',
        'marudi:mulu', 'miri:bakam', 'miri:lambir', 'subis:sepupok', 'subis:suai',
        'telang-usan:lio-matu', 'telang-usan:long-akah', 'telang-usan:long-bedian',
        'telang-usan:long-san', 'dalat:batang-igan', 'daro:paloh', 'daro:semah',
        'daro:serdeng', 'matu:igan', 'matu:jemoreng', 'tanjung-manis:tanjung-manis',
        'asajaya:moyan', 'asajaya:semera', 'asajaya:tambirat', 'gedong:gedong',
        'kota-samarahan:muara-tuang', 'simunjan:rangawan', 'simunjan:terasi',
        'julau:meluan', 'julau:nanga-entabai', 'meradong:nyelong', 'meradong:tulai',
        'pakan:wuak', 'sarikei:jakar', 'sarikei:repok', 'serian:balai-ringin',
        'serian:tebakang', 'siburan:tapah', 'tebedu:amo', 'kanowit:machan',
        'kanowit:majau', 'kanowit:nanga-dap', 'kanowit:nanga-tada', 'selangau:arip',
        'selangau:tamin', 'sibu:kemuyang', 'sibu:pasai-siong', 'sibu:pulau-babi',
        'sibu:sungai-merah', 'lubok-antu:lemanak', 'lubok-antu:skrang',
        'sri-aman:batu-lintang', 'sri-aman:undop',
    ];

    foreach ($removed as $suffix) {
        expect($areas->has('my:subdistrict:district:sarawak:' . $suffix))->toBeFalse($suffix);
    }
});

it('places the Sarawak rectified postcodes on exactly one primary area', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MY', $dir . '/malaysia-postal-codes.csv', $dir . '/malaysia-postal-code-areas.csv', 'aiarmada_addressing_malaysia_v1');

    $byCode = $source->postalCodes()->collect()->groupBy->code;

    // Phantom Lundu overflow code is gone entirely.
    expect($byCode->has('94111'))->toBeFalse();

    $primaries = static fn (string $code): array => $byCode->get($code, collect())
        ->filter(static fn (PostalCodeData $row): bool => $row->isPrimary)
        ->map(static fn (PostalCodeData $row): string => (string) $row->areaSourceId)
        ->all();

    expect($primaries('95500'))->toBe(['my:subdistrict:district:sarawak:betong:pekan-debak'])
        ->and($primaries('95600'))->toBe(['my:subdistrict:district:sarawak:betong:pekan-spaoh'])
        ->and($primaries('95300'))->toBe(['my:subdistrict:district:sarawak:kabong:pekan-roban'])
        ->and($primaries('98800'))->toBe(['my:subdistrict:district:sarawak:lawas:pekan-sundar'])
        ->and($primaries('98750'))->toBe(['my:subdistrict:district:sarawak:limbang:pekan-nanga-medamit'])
        ->and($primaries('96350'))->toBe(['my:subdistrict:district:sarawak:mukah:pekan-balingian'])
        ->and($primaries('95800'))->toBe(['my:subdistrict:district:sarawak:lubok-antu:pekan-engkilili'])
        ->and($primaries('98300'))->toBe(['my:subdistrict:district:sarawak:telang-usan:pekan-long-lama'])
        ->and($primaries('98060'))->toBe(['my:subdistrict:district:sarawak:marudi:pekan-bario'])
        ->and($primaries('94950'))->toBe(['my:subdistrict:district:sarawak:pusa:pusa'])
        ->and($primaries('97100'))->toBe(['my:subdistrict:district:sarawak:sebauh:sebauh'])
        ->and($primaries('97200'))->toBe(['my:subdistrict:district:sarawak:tatau:tatau'])
        ->and($primaries('98100'))->toBe(['my:subdistrict:district:sarawak:miri:lutong'])
        ->and($primaries('98200'))->toBe(['my:subdistrict:district:sarawak:subis:niah'])
        ->and($primaries('96010'))->toBe(['my:subdistrict:district:sarawak:sibu:sibu-jaya']);

    $secondaries = static fn (string $code): array => $byCode->get($code, collect())
        ->reject(static fn (PostalCodeData $row): bool => $row->isPrimary)
        ->map(static fn (PostalCodeData $row): string => (string) $row->areaSourceId)
        ->all();

    expect($secondaries('98850'))->toContain('my:subdistrict:district:sarawak:lawas:pekan-trusan')
        ->and($secondaries('94600'))->toContain('my:subdistrict:district:sarawak:asajaya:pekan-sadong-jaya')
        ->and($secondaries('94500'))->toContain('my:subdistrict:district:sarawak:lundu:pekan-sematan')
        ->and($secondaries('96410'))->toContain('my:subdistrict:district:sarawak:dalat:pekan-oya')
        ->and($secondaries('98050'))->toContain('my:subdistrict:district:sarawak:marudi:pekan-bario')
        ->and($secondaries('98000'))->toContain('my:subdistrict:district:sarawak:beluru:beluru')
        ->and($secondaries('95000'))->toContain('my:subdistrict:district:sarawak:pantu:pantu')
        ->and($secondaries('96000'))->toContain('my:subdistrict:district:sarawak:selangau:selangau')
        ->and($secondaries('96100'))->toContain('my:subdistrict:district:sarawak:pakan:pakan')
        ->and($secondaries('94760'))->toContain('my:subdistrict:district:sarawak:tebedu:tebedu')
        ->and($secondaries('98200'))->toContain('my:subdistrict:district:sarawak:subis:batu-niah');

    expect($secondaries('98850'))->not->toContain('my:subdistrict:district:sarawak:lawas:trusan')
        ->and($secondaries('94600'))->not->toContain('my:subdistrict:district:sarawak:asajaya:sadong-jaya')
        ->and($secondaries('94500'))->not->toContain('my:subdistrict:district:sarawak:lundu:sematan');
});

it('exposes level-4 Borneo postal localities under the region', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;
    $cenderawasih = $areas->get('my:subdistrict:district:sabah:lahad-datu:cenderawasih');

    expect($cenderawasih->type)->toBe('locality')
        ->and($cenderawasih->level)->toBe(4)
        ->and($cenderawasih->parentSourceId)->toBe('my:district:sabah:lahad-datu');

    $links = $provider->areaRelationships(new AddressCountry)['my:subdistrict:district:sabah:lahad-datu:cenderawasih'];

    expect($links)->toContain(
        ['parent_source_id' => 'my:district:sabah:lahad-datu', 'relationship_type' => 'contains', 'hierarchy_type' => 'postal'],
        ['parent_source_id' => 'my:state:sabah', 'relationship_type' => 'contains', 'hierarchy_type' => 'postal'],
    );
});
