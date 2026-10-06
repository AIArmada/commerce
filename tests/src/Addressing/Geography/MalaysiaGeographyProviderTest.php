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

it('exposes the Negeri Sembilan expansion town', function (): void {
    // Lubok China left this sweep: the Melaka book-to-row pass proved it a
    // gazetted UPI pekan, so it now carries the administrative_subdivision
    // role instead. It is covered by the Melaka book-to-row test.
    $provider = app(MalaysiaGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    $telokKemang = $areas->get('my:subdistrict:district:negeri-sembilan:port-dickson:telok-kemang');

    expect($telokKemang->type)->toBe('locality')
        ->and($telokKemang->level)->toBe(3)
        ->and($telokKemang->parentSourceId)->toBe('my:district:negeri-sembilan:port-dickson');

    $country = new AddressCountry;
    $roles = $provider->areaRoles($country);

    expect($roles['my:subdistrict:district:negeri-sembilan:port-dickson:telok-kemang'][0]['role'])->toBe('postal_locality');
});

it('exposes the Perlis and Penang expansion towns', function (): void {
    // Tikam Batu left this sweep: the Kedah book-to-row pass proved it a
    // gazetted UPI bandar, so it now carries the administrative_subdivision
    // role instead. It is covered by the Kedah book-to-row test.
    // Kangar and Kaki Bukit likewise: the Perlis book-to-row pass proved
    // them gazetted UPI town (bandar/pekan), covered by the Perlis test.
    $provider = app(MalaysiaGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('my:subdistrict:state:perlis:padang-besar')->parentSourceId)->toBe('my:state:perlis')
        ->and($areas->get('my:subdistrict:state:perlis:simpang-empat')->parentSourceId)->toBe('my:state:perlis')
        ->and($areas->get('my:subdistrict:district:pulau-pinang:barat-daya:teluk-bahang')->parentSourceId)->toBe('my:district:pulau-pinang:barat-daya')
        ->and($areas->get('my:subdistrict:district:pulau-pinang:seberang-perai-selatan:batu-kawan')->parentSourceId)->toBe('my:district:pulau-pinang:seberang-perai-selatan')
        ->and($areas->get('my:subdistrict:district:pulau-pinang:seberang-perai-utara:bertam')->parentSourceId)->toBe('my:district:pulau-pinang:seberang-perai-utara');

    $country = new AddressCountry;
    $roles = $provider->areaRoles($country);

    expect($roles['my:subdistrict:state:perlis:padang-besar'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:state:perlis:simpang-empat'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:district:pulau-pinang:barat-daya:teluk-bahang'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:district:pulau-pinang:seberang-perai-selatan:batu-kawan'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:district:pulau-pinang:seberang-perai-utara:bertam'][0]['role'])->toBe('postal_locality');

    $links = $provider->areaRelationships($country)['my:subdistrict:state:perlis:padang-besar'];

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
        ->and($areas->get('my:subdistrict:district:perak:kerian:simpang-lima')->parentSourceId)->toBe('my:district:perak:kerian')
        ->and($areas->get('my:subdistrict:district:selangor:petaling:seri-kembangan')->parentSourceId)->toBe('my:district:selangor:petaling')
        ->and($areas->get('my:subdistrict:district:selangor:petaling:serdang')->parentSourceId)->toBe('my:district:selangor:petaling')
        ->and($areas->get('my:subdistrict:district:selangor:hulu-langat:balakong')->parentSourceId)->toBe('my:district:selangor:hulu-langat');

    $country = new AddressCountry;
    $roles = $provider->areaRoles($country);

    // Mengkarak left this sweep: the Pahang book-to-row pass proved it a gazetted
    // UPI pekan, so it now carries the administrative_subdivision role instead.
    // Simpang Lima likewise: the Perak book-to-row pass retyped it locality to pekan.
    // Serdang and Balakong likewise: the Selangor book-to-row pass proved them
    // gazetted UPI pekan and bandar respectively.
    expect($roles['my:subdistrict:district:pahang:bentong:bukit-tinggi'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:district:perak:kerian:simpang-lima'][0]['role'])->toBe('administrative_subdivision')
        ->and($roles['my:subdistrict:district:selangor:petaling:seri-kembangan'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:district:selangor:petaling:serdang'][0]['role'])->toBe('administrative_subdivision')
        ->and($roles['my:subdistrict:district:selangor:hulu-langat:balakong'][0]['role'])->toBe('administrative_subdivision');
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
        'kota-marudu:pingan-pingan' => 'kota-marudu',
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
        ->and($roles['my:subdistrict:district:sabah:tawau:merotai-besar'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:district:sabah:kota-marudu:pingan-pingan'][0]['role'])->toBe('postal_locality');

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

    // Pingan Pingan was once treated as a phantom Kota Marudu overflow,
    // but it is a real Pos Malaysia office set: delivery code 89130 plus
    // PO-box/window/lockbag codes 89137-89139, each with its own
    // postcode.my locality page (Kota Marudu, Sabah), and all four sit in
    // a Pos-live cell (Kota Marudu, Oct 2026 probe). The rectification
    // directories simply do not cover this office.

    $primaries = static fn (string $code): array => $byCode->get($code, collect())
        ->filter(static fn (PostalCodeData $row): bool => $row->isPrimary)
        ->map(static fn (PostalCodeData $row): string => (string) $row->areaSourceId)
        ->all();

    expect($primaries('89250'))->toBe(['my:subdistrict:district:sabah:tuaran:pekan-tamparuli'])
        ->and($primaries('89760'))->toBe(['my:subdistrict:district:sabah:kuala-penyu:pekan-menumbok'])
        ->and($primaries('89720'))->toBe(['my:subdistrict:district:sabah:membakut:pekan-membakut'])
        ->and($primaries('89500'))->toBe(['my:subdistrict:district:sabah:penampang:pekan-donggongon'])
        ->and($primaries('90400'))->toBe(['my:subdistrict:district:sabah:paitan:pamol'])
        ->and($primaries('91150'))->toBe(['my:subdistrict:district:sabah:lahad-datu:cenderawasih'])
        ->and($primaries('89130'))->toBe(['my:subdistrict:district:sabah:kota-marudu:pingan-pingan'])
        ->and($primaries('89137'))->toBe(['my:subdistrict:district:sabah:kota-marudu:pingan-pingan'])
        ->and($primaries('89138'))->toBe(['my:subdistrict:district:sabah:kota-marudu:pingan-pingan'])
        ->and($primaries('89139'))->toBe(['my:subdistrict:district:sabah:kota-marudu:pingan-pingan']);

    $secondaries = static fn (string $code): array => $byCode->get($code, collect())
        ->reject(static fn (PostalCodeData $row): bool => $row->isPrimary)
        ->map(static fn (PostalCodeData $row): string => (string) $row->areaSourceId)
        ->all();

    // Tandek left 89050 in the Oct-2026 retry: MOH addressed
    // "PEKAN TANDEK 89100" + school PO-box zone + directory 89100
    // beat the directory-only 89050 (zero addressed usage).
    expect($secondaries('89050'))->toContain(
        'my:subdistrict:district:sabah:kudat:pekan-karakit',
        'my:subdistrict:district:sabah:kudat:pekan-sikuati',
        'my:subdistrict:district:sabah:kudat:pekan-matunggong',
        'my:subdistrict:district:sabah:kota-marudu:langkon',
    )->not->toContain(
        'my:subdistrict:district:sabah:kudat:banggi',
        'my:subdistrict:district:sabah:kudat:matunggong',
        'my:subdistrict:district:sabah:kota-marudu:tandek',
    );

    expect($secondaries('89100'))->toContain(
        'my:subdistrict:district:sabah:kota-marudu:tandek',
    );

    expect($secondaries('91150'))->toBeEmpty();
});

it('exposes Sarawak daerah kecil as the administrative subdivision tier', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    // Padawan rejoined as the 18th DK in the Oct-2026 L3-program pass:
    // the "is a district" removal premise was rebutted (state portal
    // sub-district column + Kuching division DK office + 11-Aug-1983
    // gazette history), so it is a gazetted DK under Kuching again.
    foreach (['betong:debak', 'betong:spaoh', 'kabong:roban', 'pusa:maludam', 'saratok:nanga-budu', 'lundu:sematan', 'lawas:sundar', 'lawas:trusan', 'limbang:nanga-medamit', 'telang-usan:long-lama', 'dalat:oya', 'mukah:balingian', 'lubok-antu:engkilili', 'asajaya:sadong-jaya', 'marudi:bario', 'subis:sibuti', 'subis:niah-suai', 'kuching:padawan'] as $suffix) {
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
        'pantu:lachau' => 'pantu',
        'pantu:sungai-tenggang' => 'pantu',
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
        'kuching:santubong', 'kuching:semariang', 'lundu:biawak',
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

    // 94111 was once treated as a phantom Lundu overflow, but it is
    // Tanjung Datu's special postcode: Pos Malaysia issued it to the
    // Tanjung Datu Lighthouse with Malaysia Book of Records recognition
    // (Sarawak Tribune, Nov 2024), so it resolves on Lundu town. Its
    // primary is asserted in the chain below.

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
        ->and($primaries('96010'))->toBe(['my:subdistrict:district:sarawak:sibu:sibu-jaya'])
        ->and($primaries('94111'))->toBe(['my:subdistrict:district:sarawak:lundu:lundu']);

    $secondaries = static fn (string $code): array => $byCode->get($code, collect())
        ->reject(static fn (PostalCodeData $row): bool => $row->isPrimary)
        ->map(static fn (PostalCodeData $row): string => (string) $row->areaSourceId)
        ->all();

    // Oct-2026 retry: Pakan owns 96510 primary (gazette x2 + bank x2
    // + school) and lost its directory-only 96100 secondary; Beluru
    // gains a 98050 dual leg (gazette + Sarawak-gov SKAS + school);
    // Lachau + Sungai Tenggang join 95000 as new Pantu pekan rows.
    expect($primaries('96510'))->toBe(['my:subdistrict:district:sarawak:pakan:pakan'])
        ->and($primaries('96100'))->toBe(['my:subdistrict:district:sarawak:sarikei:sarikei']);

    expect($secondaries('98850'))->toContain('my:subdistrict:district:sarawak:lawas:pekan-trusan')
        ->and($secondaries('94600'))->toContain('my:subdistrict:district:sarawak:asajaya:pekan-sadong-jaya')
        ->and($secondaries('94500'))->toContain('my:subdistrict:district:sarawak:lundu:pekan-sematan')
        ->and($secondaries('96410'))->toContain('my:subdistrict:district:sarawak:dalat:pekan-oya')
        ->and($secondaries('98050'))->toContain('my:subdistrict:district:sarawak:marudi:pekan-bario')
        ->and($secondaries('98050'))->toContain('my:subdistrict:district:sarawak:beluru:beluru')
        ->and($secondaries('98000'))->toContain('my:subdistrict:district:sarawak:beluru:beluru')
        ->and($secondaries('95000'))->toContain('my:subdistrict:district:sarawak:pantu:pantu')
        ->and($secondaries('95000'))->toContain('my:subdistrict:district:sarawak:pantu:lachau')
        ->and($secondaries('95000'))->toContain('my:subdistrict:district:sarawak:pantu:sungai-tenggang')
        ->and($secondaries('96000'))->toContain('my:subdistrict:district:sarawak:selangau:selangau')
        ->and($secondaries('94760'))->toContain('my:subdistrict:district:sarawak:tebedu:tebedu')
        ->and($secondaries('98200'))->toContain('my:subdistrict:district:sarawak:subis:batu-niah');

    expect($secondaries('98850'))->not->toContain('my:subdistrict:district:sarawak:lawas:trusan')
        ->and($secondaries('94600'))->not->toContain('my:subdistrict:district:sarawak:asajaya:sadong-jaya')
        ->and($secondaries('94500'))->not->toContain('my:subdistrict:district:sarawak:lundu:sematan')
        ->and($secondaries('96100'))->not->toContain('my:subdistrict:district:sarawak:pakan:pakan');
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

it('exposes the Kelantan UPI bandars alongside their same-stem mukims', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    // UPI lists both MUKIM X and BANDAR X here; the bandar keeps the prefixed
    // name because the bare name belongs to the mukim (Johor Kluang precedent).
    foreach (['bachok', 'tumpat', 'pasir-puteh', 'kuala-krai', 'machang', 'gua-musang', 'tanah-merah'] as $district) {
        $bandar = $areas->get("my:subdistrict:district:kelantan:{$district}:bandar-{$district}");

        expect($bandar->type)->toBe('bandar', $district)
            ->and($bandar->level)->toBe(3, $district)
            ->and($bandar->parentSourceId)->toBe("my:district:kelantan:{$district}", $district);
    }

    // Tanah Merah type swap: the bare row is the UPI mukim, the prefixed row the bandar.
    expect($areas->get('my:subdistrict:district:kelantan:tanah-merah:tanah-merah')->type)->toBe('mukim');

    // Pasir Mas has no UPI mukim, so the bare bandar stands alone and the dup row is gone.
    expect($areas->get('my:subdistrict:district:kelantan:pasir-mas:pasir-mas')->type)->toBe('bandar')
        ->and($areas->has('my:subdistrict:district:kelantan:pasir-mas:bandar-pasir-mas'))->toBeFalse();

    $roles = $provider->areaRoles(new AddressCountry);

    expect($roles['my:subdistrict:district:kelantan:tumpat:bandar-tumpat'][0]['role'])->toBe('administrative_subdivision')
        ->and($roles['my:subdistrict:district:kelantan:tanah-merah:tanah-merah'][0]['role'])->toBe('administrative_subdivision');
});

it('exposes the Kelantan book-to-row completed mukim tiers', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    foreach ([
        'kota-bharu:aur-duri', 'kota-bharu:che-latiff', 'kota-bharu:duson-rendah',
        'kota-bharu:kampung-sireh', 'kota-bharu:ketereh-barat', 'kota-bharu:ketereh-timor',
        'kota-bharu:pasir-mas', 'kota-bharu:pulau', 'kota-bharu:telok-bharu',
        'bachok:gajah-mati', 'bachok:temu-ranggas', 'bachok:tualang-salak',
        'pasir-mas:apa-apa', 'pasir-mas:kuala-kelar',
        'pasir-puteh:gong-chapa', 'pasir-puteh:gong-pachat', 'pasir-puteh:pengkalan',
        'tumpat:wakaf-delima', 'lojing:balar', 'lojing:sigar',
    ] as $suffix) {
        $area = $areas->get('my:subdistrict:district:kelantan:' . $suffix);

        expect($area->type)->toBe('mukim', $suffix)
            ->and($area->level)->toBe(3, $suffix);
    }

    expect($areas->get('my:subdistrict:district:kelantan:lojing:balar')->parentSourceId)->toBe('my:district:kelantan:lojing')
        ->and($areas->get('my:subdistrict:district:kelantan:pasir-mas:apa-apa')->name)->toBe('Apa-Apa');

    // Full-tier counts pin the UPI completion: 89 Kota Bharu mukims, 7 Lojing mukims.
    $forParent = static fn (string $parent): int => $areas
        ->filter(static fn (AddressAreaData $area): bool => $area->parentSourceId === $parent && $area->type === 'mukim')
        ->count();

    expect($forParent('my:district:kelantan:kota-bharu'))->toBe(89)
        ->and($forParent('my:district:kelantan:lojing'))->toBe(7);

    // Kampung spelling choice over the UPI Kampong form, matching the two older rows.
    $names = $provider->areaNames(new AddressCountry);

    expect($names['my:subdistrict:district:kelantan:kota-bharu:kampung-sireh'])->toContain(
        ['name' => 'Kampong Sireh', 'name_type' => 'alternative'],
    );
});

it('places the Kelantan pekan primaries on the pekan rows', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MY', $dir . '/malaysia-postal-codes.csv', $dir . '/malaysia-postal-code-areas.csv', 'aiarmada_addressing_malaysia_v1');

    $byCode = $source->postalCodes()->collect()->groupBy->code;

    $primaries = static fn (string $code): array => $byCode->get($code, collect())
        ->filter(static fn (PostalCodeData $row): bool => $row->isPrimary)
        ->map(static fn (PostalCodeData $row): string => (string) $row->areaSourceId)
        ->all();

    expect($primaries('17200'))->toBe(['my:subdistrict:district:kelantan:pasir-mas:pekan-rantau-panjang'])
        ->and($primaries('18400'))->toBe(['my:subdistrict:district:kelantan:machang:pekan-temangan'])
        ->and($primaries('16810'))->toBe(['my:subdistrict:district:kelantan:pasir-puteh:pekan-selising'])
        ->and($primaries('16070'))->toBe(['my:subdistrict:district:kelantan:bachok:jelawat']);

    // Pasir Mas town codes consolidated on the bare bandar after the dup removal.
    expect($primaries('17000'))->toBe(['my:subdistrict:district:kelantan:pasir-mas:pasir-mas'])
        ->and($primaries('17070'))->toBe(['my:subdistrict:district:kelantan:pasir-mas:pasir-mas']);

    $secondaries = static fn (string $code): array => $byCode->get($code, collect())
        ->reject(static fn (PostalCodeData $row): bool => $row->isPrimary)
        ->map(static fn (PostalCodeData $row): string => (string) $row->areaSourceId)
        ->all();

    // Tanah Merah town codes stay primary on the bandar, secondary on the swapped mukim.
    expect($primaries('17500'))->toBe(['my:subdistrict:district:kelantan:tanah-merah:bandar-tanah-merah'])
        ->and($secondaries('17500'))->toContain('my:subdistrict:district:kelantan:tanah-merah:tanah-merah');
});

it('exposes the Pahang book-to-row completed bandar and pekan tiers', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    foreach ([
        'bentong:bandar-bentong' => 'bandar', 'bentong:telemung' => 'pekan',
        'cameron-highlands:bandar-tanah-rata' => 'bandar', 'cameron-highlands:lubok-tamang' => 'pekan',
        'cameron-highlands:pekan-ringlet' => 'pekan', 'jerantut:pekan-kuala-tembeling' => 'pekan',
        'jerantut:jeransang' => 'pekan', 'kuantan:pekan-beserah' => 'pekan',
        'kuantan:tanjung-lumpur' => 'pekan', 'lipis:bandar-kuala-lipis' => 'bandar',
        'pekan:bandar-pekan' => 'bandar', 'pekan:pekan-kuala-pahang' => 'pekan',
        'pekan:nenasi' => 'pekan', 'raub:pekan-raub' => 'pekan', 'raub:pekan-dong' => 'pekan',
        'raub:pekan-tras' => 'pekan', 'raub:cheroh' => 'pekan', 'raub:sang-lee' => 'pekan',
        'raub:sungai-ruan' => 'pekan', 'raub:sungai-kelau' => 'pekan',
        'temerloh:bandar-mentakab' => 'bandar', 'temerloh:pekan-kerdau' => 'pekan',
        'rompin:baharu-rompin' => 'bandar', 'rompin:rompin-i' => 'bandar',
        'rompin:rompin-ii' => 'bandar', 'rompin:rompin-iii' => 'bandar',
        'rompin:rompin-iv' => 'bandar', 'rompin:bandar-pontian' => 'bandar',
        'rompin:bandar-endau' => 'bandar', 'rompin:bandar-tioman' => 'bandar',
        'rompin:pekan-tioman' => 'pekan', 'maran:pekan-chenor' => 'pekan',
        'maran:sri-jaya' => 'pekan', 'bera:bandar-triang' => 'bandar',
        'bera:durian-tawar' => 'pekan', 'bera:mengkuang' => 'pekan',
    ] as $suffix => $type) {
        $area = $areas->get('my:subdistrict:district:pahang:' . $suffix);

        expect($area->type)->toBe($type, $suffix)
            ->and($area->level)->toBe(3, $suffix);
    }

    // Retypes: Gambang was a mistyped mukim, Benta/Padang Tengku are UPI pekans,
    // and the Mengkarak postal locality is a gazetted UPI pekan.
    expect($areas->get('my:subdistrict:district:pahang:kuantan:gambang')->type)->toBe('bandar')
        ->and($areas->get('my:subdistrict:district:pahang:lipis:benta')->type)->toBe('pekan')
        ->and($areas->get('my:subdistrict:district:pahang:lipis:padang-tengku')->type)->toBe('pekan')
        ->and($areas->get('my:subdistrict:district:pahang:bera:mengkarak')->type)->toBe('pekan');

    // Full-tier counts pin the UPI completion.
    $forParent = static fn (string $parent): int => $areas
        ->filter(static fn (AddressAreaData $area): bool => $area->parentSourceId === $parent && in_array($area->type, ['mukim', 'bandar', 'pekan'], true))
        ->count();

    expect($forParent('my:district:pahang:rompin'))->toBe(14)
        ->and($forParent('my:district:pahang:raub'))->toBe(16)
        ->and($forParent('my:district:pahang:cameron-highlands'))->toBe(7)
        ->and($forParent('my:district:pahang:bera'))->toBe(6);

    $names = $provider->areaNames(new AddressCountry);

    expect($names['my:subdistrict:district:pahang:bentong:telemung'])->toContain(
        ['name' => 'Telemong', 'name_type' => 'alternative'],
    );
});

it('places the Pahang town-code primaries on the bandar and pekan rows', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MY', $dir . '/malaysia-postal-codes.csv', $dir . '/malaysia-postal-code-areas.csv', 'aiarmada_addressing_malaysia_v1');

    $byCode = $source->postalCodes()->collect()->groupBy->code;

    $primaries = static fn (string $code): array => $byCode->get($code, collect())
        ->filter(static fn (PostalCodeData $row): bool => $row->isPrimary)
        ->map(static fn (PostalCodeData $row): string => (string) $row->areaSourceId)
        ->all();

    expect($primaries('39000'))->toBe(['my:subdistrict:district:pahang:cameron-highlands:bandar-tanah-rata'])
        ->and($primaries('39200'))->toBe(['my:subdistrict:district:pahang:cameron-highlands:pekan-ringlet'])
        ->and($primaries('26600'))->toBe(['my:subdistrict:district:pahang:pekan:bandar-pekan'])
        ->and($primaries('28400'))->toBe(['my:subdistrict:district:pahang:temerloh:bandar-mentakab'])
        ->and($primaries('28100'))->toBe(['my:subdistrict:district:pahang:maran:pekan-chenor'])
        ->and($primaries('28300'))->toBe(['my:subdistrict:district:pahang:bera:bandar-triang'])
        ->and($primaries('27400'))->toBe(['my:subdistrict:district:pahang:raub:pekan-dong'])
        ->and($primaries('27500'))->toBe(['my:subdistrict:district:pahang:raub:sungai-ruan'])
        ->and($primaries('28700'))->toBe(['my:subdistrict:district:pahang:bentong:bandar-bentong'])
        ->and($primaries('26100'))->toBe(['my:subdistrict:district:pahang:kuantan:pekan-beserah']);

    // 26150 has no Beserah-town evidence, so it stays primary on Sungai Karang.
    expect($primaries('26150'))->toBe(['my:subdistrict:district:pahang:kuantan:sungai-karang']);
});

it('exposes the Johor book-to-row completed bandar and pekan tiers', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    foreach ([
        'johor-bahru:bandar-tebrau' => 'bandar',
        'kluang:bandar-paloh' => 'bandar', 'kluang:bandar-rengam' => 'bandar',
        'mersing:bandar-jemaluang' => 'bandar', 'mersing:mersing-kanan' => 'bandar',
        'mersing:bandar-padang-endau' => 'bandar', 'muar:bandar-bukit-kepong' => 'bandar',
        'muar:bandar-parit-jawa' => 'bandar', 'pontian:bandar-benut' => 'bandar',
        'segamat:bandar-bekok' => 'bandar', 'segamat:bandar-buloh-kasap' => 'bandar',
        'segamat:bandar-jementah' => 'bandar', 'segamat:bandar-labis' => 'bandar',
        'segamat:gemas-bahru' => 'pekan', 'tangkak:bukit-kangkar' => 'bandar',
        'tangkak:parit-bunga' => 'bandar', 'tangkak:bandar-serom' => 'bandar',
        'tangkak:pekan-grisek' => 'pekan',
    ] as $suffix => $type) {
        $area = $areas->get('my:subdistrict:district:johor:' . $suffix);

        expect($area->type)->toBe($type, $suffix)
            ->and($area->level)->toBe(3, $suffix);
    }

    // Panchor was a mistyped mukim: UPI lists only Bandar Panchor (06/43).
    expect($areas->get('my:subdistrict:district:johor:muar:panchor')->type)->toBe('bandar');

    // The Bandar Segamat mukim duplicate is gone (UPI has no such entity).
    expect($areas->has('my:subdistrict:district:johor:segamat:bandar-segamat'))->toBeFalse();

    // Full-tier counts pin the UPI completion.
    $forParent = static fn (string $parent): int => $areas
        ->filter(static fn (AddressAreaData $area): bool => $area->parentSourceId === $parent && in_array($area->type, ['mukim', 'bandar', 'pekan'], true))
        ->count();

    expect($forParent('my:district:johor:batu-pahat'))->toBe(19)
        ->and($forParent('my:district:johor:johor-bahru'))->toBe(8)
        ->and($forParent('my:district:johor:kluang'))->toBe(11)
        ->and($forParent('my:district:johor:kota-tinggi'))->toBe(11)
        ->and($forParent('my:district:johor:mersing'))->toBe(18)
        ->and($forParent('my:district:johor:muar'))->toBe(17)
        ->and($forParent('my:district:johor:pontian'))->toBe(14)
        ->and($forParent('my:district:johor:segamat'))->toBe(18)
        ->and($forParent('my:district:johor:kulai'))->toBe(5)
        ->and($forParent('my:district:johor:tangkak'))->toBe(12);

    $names = $provider->areaNames(new AddressCountry);

    expect($names['my:subdistrict:district:johor:kluang:bandar-rengam'])->toContain(
        ['name' => 'Renggam', 'name_type' => 'alternative'],
    )->and($names['my:subdistrict:district:johor:tangkak:pekan-grisek'])->toContain(
        ['name' => 'Gerisek', 'name_type' => 'alternative'],
    )->and($names['my:subdistrict:district:johor:segamat:gemas-bahru'])->toContain(
        ['name' => 'Gemas Baru', 'name_type' => 'alternative'],
    );
});

it('places the Johor town-code primaries on the bandar and pekan rows', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MY', $dir . '/malaysia-postal-codes.csv', $dir . '/malaysia-postal-code-areas.csv', 'aiarmada_addressing_malaysia_v1');

    $byCode = $source->postalCodes()->collect()->groupBy->code;

    $primaries = static fn (string $code): array => $byCode->get($code, collect())
        ->filter(static fn (PostalCodeData $row): bool => $row->isPrimary)
        ->map(static fn (PostalCodeData $row): string => (string) $row->areaSourceId)
        ->all();

    expect($primaries('82200'))->toBe(['my:subdistrict:district:johor:pontian:bandar-benut'])
        ->and($primaries('84150'))->toBe(['my:subdistrict:district:johor:muar:bandar-parit-jawa'])
        ->and($primaries('84160'))->toBe(['my:subdistrict:district:johor:muar:bandar-parit-jawa'])
        ->and($primaries('86600'))->toBe(['my:subdistrict:district:johor:kluang:bandar-paloh'])
        ->and($primaries('86300'))->toBe(['my:subdistrict:district:johor:kluang:bandar-rengam'])
        ->and($primaries('85200'))->toBe(['my:subdistrict:district:johor:segamat:bandar-jementah'])
        ->and($primaries('85210'))->toBe(['my:subdistrict:district:johor:segamat:bandar-jementah'])
        ->and($primaries('85220'))->toBe(['my:subdistrict:district:johor:segamat:bandar-jementah'])
        ->and($primaries('85300'))->toBe(['my:subdistrict:district:johor:segamat:bandar-labis'])
        ->and($primaries('86500'))->toBe(['my:subdistrict:district:johor:segamat:bandar-bekok'])
        ->and($primaries('85010'))->toBe(['my:subdistrict:district:johor:segamat:bandar-buloh-kasap'])
        ->and($primaries('84700'))->toBe(['my:subdistrict:district:johor:tangkak:pekan-grisek'])
        ->and($primaries('84710'))->toBe(['my:subdistrict:district:johor:tangkak:pekan-grisek']);

    // Untouched primaries stay put: Segamat town, retyped Panchor, and the
    // Negeri Sembilan-held Gemas code shared with Gemas Bahru.
    expect($primaries('85000'))->toBe(['my:subdistrict:district:johor:segamat:segamat'])
        ->and($primaries('84500'))->toBe(['my:subdistrict:district:johor:muar:panchor'])
        ->and($primaries('73400'))->toBe(['my:subdistrict:district:negeri-sembilan:tampin:bandar-gemas']);
});

it('exposes the Kedah book-to-row completed bandar and pekan tiers', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    foreach ([
        'kota-setar:bandar-anak-bukit' => 'bandar', 'kota-setar:alor-merah' => 'bandar', 'kota-setar:bukit-pinang' => 'bandar',
        'kota-setar:bandar-langgar' => 'bandar', 'kota-setar:alor-janggus' => 'pekan', 'kota-setar:pekan-gunung' => 'pekan',
        'kubang-pasu:bandar-tunjang' => 'bandar', 'kubang-pasu:padang-sera' => 'bandar', 'kubang-pasu:kuala-sanglang' => 'pekan',
        'kubang-pasu:pekan-sanglang' => 'pekan', 'kubang-pasu:kerpan' => 'pekan', 'kubang-pasu:sintok' => 'pekan',
        'kubang-pasu:napoh' => 'pekan', 'kubang-pasu:sungai-korok' => 'pekan',
        'padang-terap:naka' => 'pekan', 'padang-terap:durian-burung' => 'pekan', 'padang-terap:lubok-merbau' => 'pekan',
        'padang-terap:bukit-tembaga' => 'pekan', 'padang-terap:padang-sanai' => 'pekan', 'padang-terap:kampung-tanjung' => 'pekan',
        'langkawi:bandar-kuah' => 'bandar', 'langkawi:bandar-padang-mat-sirat' => 'bandar', 'langkawi:padang-lalang' => 'bandar',
        'langkawi:telok-datai' => 'pekan',
        'kuala-muda:teloi-kiri' => 'mukim', 'kuala-muda:bandar-gurun' => 'bandar', 'kuala-muda:sungai-lalang' => 'bandar',
        'kuala-muda:bandar-merbok' => 'bandar', 'kuala-muda:bandar-semeling' => 'bandar', 'kuala-muda:bandar-aman-jaya' => 'bandar',
        'kuala-muda:bukit-selambau' => 'pekan', 'kuala-muda:tanjung-dawai' => 'pekan',
        'yan:bandar-yan' => 'bandar', 'yan:simpang-tiga-sungai-limau' => 'pekan', 'yan:sungai-limau' => 'pekan',
        'yan:teroi' => 'pekan', 'yan:pekan-singkir' => 'pekan',
        'sik:bandar-sik' => 'bandar', 'sik:batu-lima-sik' => 'pekan', 'sik:gulau' => 'pekan',
        'sik:gajah-puteh' => 'pekan', 'sik:charok-padang' => 'pekan',
        'baling:bandar-kupang' => 'bandar', 'baling:kampung-baru-kejai' => 'pekan', 'baling:pekan-pulai' => 'pekan',
        'baling:pekan-tawar' => 'pekan', 'baling:parit-panjang' => 'pekan', 'baling:kampung-lalang' => 'pekan',
        'baling:malau' => 'pekan',
        'kulim:pekan-junjong' => 'pekan', 'kulim:pekan-karangan' => 'pekan', 'kulim:labu-besar' => 'pekan',
        'kulim:pekan-mahang' => 'pekan', 'kulim:merbau-pulas' => 'pekan', 'kulim:sungai-karangan' => 'pekan',
        'kulim:sungai-kob' => 'pekan', 'kulim:pekan-padang-meha' => 'pekan',
        'bandar-baharu:bandar-serdang' => 'bandar', 'bandar-baharu:lubuk-buntar' => 'pekan', 'bandar-baharu:selama' => 'pekan',
        'bandar-baharu:sungai-kechil-ilir' => 'pekan', 'bandar-baharu:pekan-relau' => 'pekan',
        'pendang:bukit-raya' => 'mukim', 'pendang:bukit-jenun' => 'pekan', 'pendang:kubur-panjang' => 'pekan',
        'pendang:tanah-merah' => 'pekan', 'pendang:tokai' => 'pekan', 'pendang:kobah' => 'pekan',
        'pendang:kampung-baru' => 'pekan', 'pendang:sungai-tiang' => 'pekan',
        'pokok-sena:kebun-500' => 'pekan',
    ] as $suffix => $type) {
        $area = $areas->get('my:subdistrict:district:kedah:' . $suffix);

        expect($area->type)->toBe($type, $suffix)
            ->and($area->level)->toBe(3, $suffix);
    }

    // Retypes: four mistyped town mukims plus the Tikam Batu locality conversion.
    expect($areas->get('my:subdistrict:district:kedah:kubang-pasu:bandar-jitra')->type)->toBe('bandar')
        ->and($areas->get('my:subdistrict:district:kedah:kuala-muda:bandar-sungai-petani')->type)->toBe('bandar')
        ->and($areas->get('my:subdistrict:district:kedah:baling:bandar-baling')->type)->toBe('bandar')
        ->and($areas->get('my:subdistrict:district:kedah:kulim:bandar-kulim')->type)->toBe('bandar')
        ->and($areas->get('my:subdistrict:district:kedah:kuala-muda:tikam-batu')->type)->toBe('bandar');

    // Consolidated duplicates are gone (their links moved to the bare bandars).
    expect($areas->has('my:subdistrict:district:kedah:kota-setar:bandar-alor-setar'))->toBeFalse()
        ->and($areas->has('my:subdistrict:district:kedah:pendang:bandar-pendang'))->toBeFalse()
        ->and($areas->has('my:subdistrict:district:kedah:pokok-sena:pekan-pokok-sena'))->toBeFalse()
        ->and($areas->has('my:subdistrict:district:kedah:sik:pekan-sik'))->toBeFalse();

    // Full-tier counts pin the UPI completion (Yan 11: the book lists Guar
    // Cempedak/Chempedak as two code-40 rows for one town).
    $forParent = static fn (string $parent): int => $areas
        ->filter(static fn (AddressAreaData $area): bool => $area->parentSourceId === $parent && in_array($area->type, ['mukim', 'bandar', 'pekan'], true))
        ->count();

    expect($forParent('my:district:kedah:kota-setar'))->toBe(29)
        ->and($forParent('my:district:kedah:kubang-pasu'))->toBe(36)
        ->and($forParent('my:district:kedah:padang-terap'))->toBe(18)
        ->and($forParent('my:district:kedah:langkawi'))->toBe(10)
        ->and($forParent('my:district:kedah:kuala-muda'))->toBe(28)
        ->and($forParent('my:district:kedah:yan'))->toBe(11)
        ->and($forParent('my:district:kedah:sik'))->toBe(8)
        ->and($forParent('my:district:kedah:baling'))->toBe(18)
        ->and($forParent('my:district:kedah:kulim'))->toBe(24)
        ->and($forParent('my:district:kedah:bandar-baharu'))->toBe(13)
        ->and($forParent('my:district:kedah:pendang'))->toBe(16)
        ->and($forParent('my:district:kedah:pokok-sena'))->toBe(8);

    $names = $provider->areaNames(new AddressCountry);

    expect($names['my:subdistrict:district:kedah:langkawi:telok-datai'])->toContain(
        ['name' => 'Teluk Datai', 'name_type' => 'alternative'],
    )->and($names['my:subdistrict:district:kedah:padang-terap:lubok-merbau'])->toContain(
        ['name' => 'Lubuk Merbau', 'name_type' => 'alternative'],
    )->and($names['my:subdistrict:district:kedah:sik:gajah-puteh'])->toContain(
        ['name' => 'Gajah Putih', 'name_type' => 'alternative'],
    );
});

it('places the Kedah town-code primaries on the bandar and pekan rows', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MY', $dir . '/malaysia-postal-codes.csv', $dir . '/malaysia-postal-code-areas.csv', 'aiarmada_addressing_malaysia_v1');

    $byCode = $source->postalCodes()->collect()->groupBy->code;

    $primaries = static fn (string $code): array => $byCode->get($code, collect())
        ->filter(static fn (PostalCodeData $row): bool => $row->isPrimary)
        ->map(static fn (PostalCodeData $row): string => (string) $row->areaSourceId)
        ->all();

    expect($primaries('07000'))->toBe(['my:subdistrict:district:kedah:langkawi:bandar-kuah'])
        ->and($primaries('06500'))->toBe(['my:subdistrict:district:kedah:kota-setar:bandar-langgar'])
        ->and($primaries('08300'))->toBe(['my:subdistrict:district:kedah:kuala-muda:bandar-gurun'])
        ->and($primaries('08400'))->toBe(['my:subdistrict:district:kedah:kuala-muda:bandar-merbok'])
        ->and($primaries('06900'))->toBe(['my:subdistrict:district:kedah:yan:bandar-yan'])
        ->and($primaries('08200'))->toBe(['my:subdistrict:district:kedah:sik:bandar-sik'])
        ->and($primaries('09200'))->toBe(['my:subdistrict:district:kedah:baling:bandar-kupang'])
        ->and($primaries('09700'))->toBe(['my:subdistrict:district:kedah:kulim:pekan-karangan'])
        ->and($primaries('09800'))->toBe(['my:subdistrict:district:kedah:bandar-baharu:bandar-serdang']);

    // Consolidation keeps every town code on the surviving bare bandar.
    expect($primaries('05000'))->toBe(['my:subdistrict:district:kedah:kota-setar:alor-setar'])
        ->and($primaries('06700'))->toBe(['my:subdistrict:district:kedah:pendang:pendang'])
        ->and($primaries('06400'))->toBe(['my:subdistrict:district:kedah:pokok-sena:pokok-sena'])
        ->and($primaries('06000'))->toBe(['my:subdistrict:district:kedah:kubang-pasu:bandar-jitra'])
        ->and($primaries('08000'))->toBe(['my:subdistrict:district:kedah:kuala-muda:bandar-sungai-petani'])
        ->and($primaries('09000'))->toBe(['my:subdistrict:district:kedah:kulim:bandar-kulim'])
        ->and($primaries('09100'))->toBe(['my:subdistrict:district:kedah:baling:bandar-baling'])
        ->and($primaries('08700'))->toBe(['my:subdistrict:district:kedah:kuala-muda:jeniang']);
});

it('exposes the Melaka book-to-row completed bandar and pekan tiers', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    foreach ([
        'melaka-tengah:padang-semabok' => 'mukim', 'melaka-tengah:bandar-bukit-baru' => 'bandar', 'melaka-tengah:pekan-ayer-molek' => 'pekan',
        'melaka-tengah:pekan-batu-berendam' => 'pekan', 'melaka-tengah:pekan-bukit-rambai' => 'pekan', 'melaka-tengah:pekan-kandang' => 'pekan',
        'melaka-tengah:klebang' => 'pekan', 'melaka-tengah:pekan-paya-rumput' => 'pekan', 'melaka-tengah:pekan-sungai-udang' => 'pekan',
        'melaka-tengah:pekan-tangga-batu' => 'pekan', 'melaka-tengah:pekan-tanjong-kling' => 'pekan',
        'jasin:bandar-merlimau' => 'bandar', 'jasin:pekan-batang-malaka' => 'pekan', 'jasin:pekan-chin-chin' => 'pekan',
        'jasin:kesang-pajak' => 'pekan', 'jasin:pekan-nyalas' => 'pekan', 'jasin:pekan-selandar' => 'pekan',
        'jasin:sempang-bekoh' => 'pekan', 'jasin:pekan-sungai-rambai' => 'pekan',
        'alor-gajah:bandar-masjid-tanah' => 'bandar', 'alor-gajah:bandar-pulau-sebang' => 'bandar', 'alor-gajah:pekan-durian-tunggal' => 'pekan',
        'alor-gajah:pekan-kuala-sungai-baru' => 'pekan', 'alor-gajah:pekan-rembia' => 'pekan',
    ] as $suffix => $type) {
        $area = $areas->get('my:subdistrict:district:melaka:' . $suffix);

        expect($area->type)->toBe($type, $suffix)
            ->and($area->level)->toBe(3, $suffix);
    }

    // Lubok China converts from postal locality to gazetted pekan in place.
    expect($areas->get('my:subdistrict:district:melaka:alor-gajah:lubok-china')->type)->toBe('pekan');

    // Full-tier counts pin the UPI completion.
    $forParent = static fn (string $parent): int => $areas
        ->filter(static fn (AddressAreaData $area): bool => $area->parentSourceId === $parent && in_array($area->type, ['mukim', 'bandar', 'pekan'], true))
        ->count();

    expect($forParent('my:district:melaka:melaka-tengah'))->toBe(40)
        ->and($forParent('my:district:melaka:jasin'))->toBe(31)
        ->and($forParent('my:district:melaka:alor-gajah'))->toBe(38);

    $names = $provider->areaNames(new AddressCountry);

    expect($names['my:subdistrict:district:melaka:melaka-tengah:pekan-sungai-udang'])->toContain(
        ['name' => 'Pekan Sungei Udang', 'name_type' => 'alternative'],
    )->and($names['my:subdistrict:district:melaka:jasin:pekan-sungai-rambai'])->toContain(
        ['name' => 'Pekan Sungei Rambai', 'name_type' => 'alternative'],
    )->and($names['my:subdistrict:district:melaka:alor-gajah:pekan-kuala-sungai-baru'])->toContain(
        ['name' => 'Pekan Kuala Sungei Baru', 'name_type' => 'alternative'],
    );
});

it('places the Melaka town-code primaries on the bandar and pekan rows', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MY', $dir . '/malaysia-postal-codes.csv', $dir . '/malaysia-postal-code-areas.csv', 'aiarmada_addressing_malaysia_v1');

    $byCode = $source->postalCodes()->collect()->groupBy->code;

    $primaries = static fn (string $code): array => $byCode->get($code, collect())
        ->filter(static fn (PostalCodeData $row): bool => $row->isPrimary)
        ->map(static fn (PostalCodeData $row): string => (string) $row->areaSourceId)
        ->all();

    expect($primaries('76400'))->toBe(['my:subdistrict:district:melaka:melaka-tengah:pekan-tanjong-kling'])
        ->and($primaries('77300'))->toBe(['my:subdistrict:district:melaka:jasin:bandar-merlimau'])
        ->and($primaries('77500'))->toBe(['my:subdistrict:district:melaka:jasin:pekan-selandar'])
        ->and($primaries('78300'))->toBe(['my:subdistrict:district:melaka:alor-gajah:bandar-masjid-tanah'])
        ->and($primaries('76100'))->toBe(['my:subdistrict:district:melaka:alor-gajah:pekan-durian-tunggal'])
        ->and($primaries('78200'))->toBe(['my:subdistrict:district:melaka:alor-gajah:pekan-kuala-sungai-baru'])
        ->and($primaries('76300'))->toBe(['my:subdistrict:district:melaka:melaka-tengah:pekan-sungai-udang'])
        ->and($primaries('77400'))->toBe(['my:subdistrict:district:melaka:jasin:pekan-sungai-rambai'])
        ->and($primaries('77409'))->toBe(['my:subdistrict:district:melaka:jasin:pekan-sungai-rambai']);

    // Lubok China keeps its town code through the locality-to-pekan conversion.
    expect($primaries('78100'))->toBe(['my:subdistrict:district:melaka:alor-gajah:lubok-china']);
});

it('exposes the Negeri Sembilan book-to-row completed bandar and pekan tiers', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    foreach ([
        'jelebu:bandar-kuala-klawang' => 'bandar', 'jelebu:pekan-kuala-klawang' => 'pekan', 'jelebu:pekan-pertang' => 'pekan',
        'jelebu:titi' => 'pekan', 'jelebu:petaling' => 'pekan', 'jelebu:sungai-muntoh' => 'pekan',
        'kuala-pilah:pekan-johol' => 'pekan', 'kuala-pilah:pekan-parit-tinggi' => 'pekan', 'kuala-pilah:pekan-juasseh' => 'pekan',
        'kuala-pilah:dangi' => 'pekan', 'kuala-pilah:gunung-pasir' => 'pekan', 'kuala-pilah:senaling' => 'pekan',
        'kuala-pilah:bukit-gelugor' => 'pekan', 'kuala-pilah:melang' => 'pekan', 'kuala-pilah:air-mawang' => 'pekan',
        'kuala-pilah:dangi-baru' => 'pekan',
        'port-dickson:bandar-port-dickson' => 'bandar', 'port-dickson:pekan-port-dickson' => 'pekan', 'port-dickson:teluk-kemang' => 'bandar',
        'port-dickson:pekan-teluk-kemang' => 'pekan', 'port-dickson:pekan-pasir-panjang' => 'pekan', 'port-dickson:pengkalan-kempas' => 'pekan',
        'port-dickson:chuah' => 'pekan', 'port-dickson:pekan-linggi' => 'pekan', 'port-dickson:bukit-pelanduk' => 'pekan',
        'port-dickson:air-kuning' => 'pekan', 'port-dickson:sungai-menyala' => 'pekan', 'port-dickson:bagan-pinang' => 'pekan',
        'port-dickson:tanah-merah-utara' => 'pekan', 'port-dickson:tanah-merah-selatan' => 'pekan', 'port-dickson:jemima' => 'pekan',
        'rembau:pekan-chengkau' => 'pekan', 'rembau:pekan-pedas' => 'pekan', 'rembau:pekan-chembong' => 'pekan',
        'rembau:pekan-rembau' => 'pekan', 'rembau:kampong-batu' => 'pekan', 'rembau:lubok-china' => 'pekan',
        'rembau:seri-kota' => 'pekan', 'rembau:seri-kendong' => 'pekan', 'rembau:merbau-sembilan' => 'pekan',
        'seremban:bandar-seremban-utama' => 'bandar', 'seremban:bandar-mantin-utama' => 'bandar', 'seremban:bandar-baru-kota-sri-mas' => 'bandar',
        'seremban:bandar-nilai-utama' => 'bandar', 'seremban:bandar-sri-sendayan' => 'bandar', 'seremban:pekan-labu' => 'pekan',
        'seremban:pekan-lenggeng' => 'pekan', 'seremban:pekan-rantau' => 'pekan', 'seremban:pekan-setul' => 'pekan',
        'seremban:broga' => 'pekan', 'seremban:ulu-beranang' => 'pekan', 'seremban:mambau' => 'pekan',
        'seremban:pajam' => 'pekan', 'seremban:tiroi' => 'pekan', 'seremban:pancor' => 'pekan',
        'seremban:taman-seremban' => 'pekan', 'seremban:rahang-baru' => 'pekan', 'seremban:paroi' => 'pekan',
        'seremban:bukit-kepayang' => 'pekan', 'seremban:dusun-setia' => 'pekan', 'seremban:sungai-gadut' => 'pekan',
        'seremban:bukti' => 'pekan', 'seremban:sikamat' => 'pekan', 'seremban:shah-bandar' => 'pekan',
        'seremban:ulu-temiang' => 'pekan', 'seremban:paroi-jaya' => 'pekan', 'seremban:rasah-jaya' => 'pekan',
        'seremban:seremban-jaya' => 'pekan',
        'tampin:bandar-gemas' => 'bandar', 'tampin:pekan-tampin-tengah' => 'pekan', 'tampin:pekan-air-kuning' => 'pekan',
        'tampin:pekan-repah' => 'pekan', 'tampin:air-kuning-selatan' => 'pekan', 'tampin:batang-melaka' => 'pekan',
        'tampin:gemencheh-bahru' => 'pekan', 'tampin:pasir-besar' => 'pekan', 'tampin:repah-jaya' => 'pekan',
        'tampin:repah-permai' => 'pekan',
        'jempol:pekan-bahau' => 'pekan', 'jempol:pekan-rompin' => 'pekan', 'jempol:kuala-jelai' => 'pekan',
        'jempol:ladang-geddes' => 'pekan', 'jempol:mahsan' => 'pekan', 'jempol:serting-tengah' => 'pekan',
        'jempol:serting' => 'bandar',
    ] as $suffix => $type) {
        $area = $areas->get('my:subdistrict:district:negeri-sembilan:' . $suffix);

        expect($area->type)->toBe($type, $suffix)
            ->and($area->level)->toBe(3, $suffix);
    }

    // Retypes: Chengkau was an inverted pekan (UPI lists mukim 04 plus pekan
    // 76); the two Bandar-prefixed mukims are the gazetted town bandars.
    expect($areas->get('my:subdistrict:district:negeri-sembilan:rembau:chengkau')->type)->toBe('mukim')
        ->and($areas->get('my:subdistrict:district:negeri-sembilan:seremban:bandar-seremban')->type)->toBe('bandar')
        ->and($areas->get('my:subdistrict:district:negeri-sembilan:jempol:bandar-seri-jempol')->type)->toBe('bandar');

    // Full-tier counts pin the UPI completion (Seremban 41: the book lists
    // Bandar Seremban under both code 40 and code 41 for one town).
    $forParent = static fn (string $parent): int => $areas
        ->filter(static fn (AddressAreaData $area): bool => $area->parentSourceId === $parent && in_array($area->type, ['mukim', 'bandar', 'pekan'], true))
        ->count();

    expect($forParent('my:district:negeri-sembilan:jelebu'))->toBe(16)
        ->and($forParent('my:district:negeri-sembilan:kuala-pilah'))->toBe(23)
        ->and($forParent('my:district:negeri-sembilan:port-dickson'))->toBe(21)
        ->and($forParent('my:district:negeri-sembilan:rembau'))->toBe(28)
        ->and($forParent('my:district:negeri-sembilan:seremban'))->toBe(41)
        ->and($forParent('my:district:negeri-sembilan:tampin'))->toBe(18)
        ->and($forParent('my:district:negeri-sembilan:jempol'))->toBe(15);

    $names = $provider->areaNames(new AddressCountry);

    expect($names['my:subdistrict:district:negeri-sembilan:rembau:kampong-batu'])->toContain(
        ['name' => 'Kampung Batu', 'name_type' => 'alternative'],
    )->and($names['my:subdistrict:district:negeri-sembilan:tampin:gemencheh-bahru'])->toContain(
        ['name' => 'Gemencheh Baru', 'name_type' => 'alternative'],
    );
});

it('places the Negeri Sembilan town-code primaries on the bandar and pekan rows', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MY', $dir . '/malaysia-postal-codes.csv', $dir . '/malaysia-postal-code-areas.csv', 'aiarmada_addressing_malaysia_v1');

    $byCode = $source->postalCodes()->collect()->groupBy->code;

    $primaries = static fn (string $code): array => $byCode->get($code, collect())
        ->filter(static fn (PostalCodeData $row): bool => $row->isPrimary)
        ->map(static fn (PostalCodeData $row): string => (string) $row->areaSourceId)
        ->all();

    expect($primaries('71600'))->toBe(['my:subdistrict:district:negeri-sembilan:jelebu:bandar-kuala-klawang'])
        ->and($primaries('73100'))->toBe(['my:subdistrict:district:negeri-sembilan:kuala-pilah:pekan-johol'])
        ->and($primaries('71000'))->toBe(['my:subdistrict:district:negeri-sembilan:port-dickson:bandar-port-dickson'])
        ->and($primaries('71150'))->toBe(['my:subdistrict:district:negeri-sembilan:port-dickson:pekan-linggi'])
        ->and($primaries('71900'))->toBe(['my:subdistrict:district:negeri-sembilan:seremban:pekan-labu'])
        ->and($primaries('71100'))->toBe(['my:subdistrict:district:negeri-sembilan:seremban:pekan-rantau'])
        ->and($primaries('73400'))->toBe(['my:subdistrict:district:negeri-sembilan:tampin:bandar-gemas'])
        ->and($primaries('73500'))->toBe(['my:subdistrict:district:negeri-sembilan:jempol:pekan-rompin']);

    // Retyped town rows keep their codes; locked-bag codes follow the town.
    expect($primaries('71300'))->toBe(['my:subdistrict:district:negeri-sembilan:rembau:rembau'])
        ->and($primaries('72100'))->toBe(['my:subdistrict:district:negeri-sembilan:jempol:bahau'])
        ->and($primaries('72120'))->toBe(['my:subdistrict:district:negeri-sembilan:jempol:bandar-seri-jempol'])
        ->and($primaries('70000'))->toBe(['my:subdistrict:district:negeri-sembilan:seremban:bandar-seremban'])
        ->and($primaries('71659'))->toBe(['my:subdistrict:district:negeri-sembilan:jelebu:bandar-kuala-klawang']);
});

it('exposes the Perak book-to-row variant spellings as alternative names', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $names = $provider->areaNames(new AddressCountry);

    expect($names['my:subdistrict:district:perak:bagan-datuk:teluk-bharu'])->toContain(
        ['name' => 'Teluk Baru', 'name_type' => 'alternative'],
    )->and($names['my:subdistrict:district:perak:bagan-datuk:batu-dua-puloh'])->toContain(
        ['name' => 'Batu Dua Puluh', 'name_type' => 'alternative'],
    )->and($names['my:subdistrict:district:perak:manjung:kampong-baharu'])->toContain(
        ['name' => 'Kampung Baharu', 'name_type' => 'alternative'],
    );
});

it('places the Perak town-code primaries on the bandar and pekan rows', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MY', $dir . '/malaysia-postal-codes.csv', $dir . '/malaysia-postal-code-areas.csv', 'aiarmada_addressing_malaysia_v1');

    $byCode = $source->postalCodes()->collect()->groupBy->code;

    $primaries = static fn (string $code): array => $byCode->get($code, collect())
        ->filter(static fn (PostalCodeData $row): bool => $row->isPrimary)
        ->map(static fn (PostalCodeData $row): string => (string) $row->areaSourceId)
        ->all();

    expect($primaries('35500'))->toBe(['my:subdistrict:district:perak:batang-padang:bandar-bidor'])
        ->and($primaries('34300'))->toBe(['my:subdistrict:district:perak:kerian:bandar-bagan-serai'])
        ->and($primaries('34200'))->toBe(['my:subdistrict:district:perak:kerian:bandar-parit-buntar'])
        ->and($primaries('34600'))->toBe(['my:subdistrict:district:perak:larut-matang:bandar-kamunting'])
        ->and($primaries('34100'))->toBe(['my:subdistrict:district:perak:selama:bandar-selama'])
        ->and($primaries('31900'))->toBe(['my:subdistrict:district:perak:kampar:bandar-kampar'])
        ->and($primaries('31050'))->toBe(['my:subdistrict:district:perak:kuala-kangsar:bandar-sungai-siput'])
        ->and($primaries('33300'))->toBe(['my:subdistrict:district:perak:hulu-perak:bandar-gerik']);

    // Retyped pekan rows keep their codes; covering mukims stay secondary-only.
    expect($primaries('34850'))->toBe(['my:subdistrict:district:perak:larut-matang:changkat-jering'])
        ->and($primaries('34140'))->toBe(['my:subdistrict:district:perak:selama:rantau-panjang'])
        ->and($primaries('31750'))->toBe(['my:subdistrict:district:perak:kinta:bandar-tronoh']);
});

it('exposes the Perlis gazetted towns as bandar and pekan rows under the state', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    // TIADA DAERAH: Perlis subdistricts hang directly under the state at level 2.
    expect($areas->get('my:subdistrict:state:perlis:bandar-arau')->type)->toBe('bandar')
        ->and($areas->get('my:subdistrict:state:perlis:bandar-arau')->level)->toBe(2)
        ->and($areas->get('my:subdistrict:state:perlis:bandar-arau')->parentSourceId)->toBe('my:state:perlis')
        ->and($areas->get('my:subdistrict:state:perlis:pekan-kuala-perlis')->type)->toBe('pekan')
        ->and($areas->get('my:subdistrict:state:perlis:pekan-kuala-perlis')->level)->toBe(2)
        ->and($areas->get('my:subdistrict:state:perlis:kangar')->type)->toBe('bandar')
        ->and($areas->get('my:subdistrict:state:perlis:kaki-bukit')->type)->toBe('pekan');

    $country = new AddressCountry;
    $roles = $provider->areaRoles($country);

    expect($roles['my:subdistrict:state:perlis:kangar'][0]['role'])->toBe('administrative_subdivision')
        ->and($roles['my:subdistrict:state:perlis:kaki-bukit'][0]['role'])->toBe('administrative_subdivision')
        ->and($roles['my:subdistrict:state:perlis:padang-besar'][0]['role'])->toBe('postal_locality')
        ->and($roles['my:subdistrict:state:perlis:simpang-empat'][0]['role'])->toBe('postal_locality');
});

it('places the Perlis town-code primaries on the bandar and pekan rows', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MY', $dir . '/malaysia-postal-codes.csv', $dir . '/malaysia-postal-code-areas.csv', 'aiarmada_addressing_malaysia_v1');

    $byCode = $source->postalCodes()->collect()->groupBy->code;

    $primaries = static fn (string $code): array => $byCode->get($code, collect())
        ->filter(static fn (PostalCodeData $row): bool => $row->isPrimary)
        ->map(static fn (PostalCodeData $row): string => (string) $row->areaSourceId)
        ->all();

    expect($primaries('02600'))->toBe(['my:subdistrict:state:perlis:bandar-arau'])
        ->and($primaries('02607'))->toBe(['my:subdistrict:state:perlis:bandar-arau'])
        ->and($primaries('02609'))->toBe(['my:subdistrict:state:perlis:bandar-arau'])
        ->and($primaries('02000'))->toBe(['my:subdistrict:state:perlis:pekan-kuala-perlis']);

    // Retyped towns keep their codes; non-gazetted towns stay postal localities.
    expect($primaries('01000'))->toBe(['my:subdistrict:state:perlis:kangar'])
        ->and($primaries('02200'))->toBe(['my:subdistrict:state:perlis:kaki-bukit'])
        ->and($primaries('02100'))->toBe(['my:subdistrict:state:perlis:padang-besar'])
        ->and($primaries('02700'))->toBe(['my:subdistrict:state:perlis:simpang-empat']);
});

it('exposes the Penang book-to-row bandars and the Sungei Bakap spelling', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('my:subdistrict:district:pulau-pinang:seberang-perai-selatan:bandar-sungai-bakap')->type)->toBe('bandar')
        ->and($areas->get('my:subdistrict:district:pulau-pinang:timur-laut:tanjong-tokong')->type)->toBe('bandar')
        ->and($areas->get('my:subdistrict:district:pulau-pinang:timur-laut:tanjong-pinang')->type)->toBe('bandar');

    $names = $provider->areaNames(new AddressCountry);

    expect($names['my:subdistrict:district:pulau-pinang:seberang-perai-selatan:bandar-sungai-bakap'])->toContain(
        ['name' => 'Bandar Sungei Bakap', 'name_type' => 'alternative'],
    );
});

it('places the 10470 primary on Tanjong Tokong and keeps Sungai Jawi principal', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MY', $dir . '/malaysia-postal-codes.csv', $dir . '/malaysia-postal-code-areas.csv', 'aiarmada_addressing_malaysia_v1');

    $byCode = $source->postalCodes()->collect()->groupBy->code;

    $primaries = static fn (string $code): array => $byCode->get($code, collect())
        ->filter(static fn (PostalCodeData $row): bool => $row->isPrimary)
        ->map(static fn (PostalCodeData $row): string => (string) $row->areaSourceId)
        ->all();

    // Every 10470 street is Tokong-area; Tanjong Pinang shares the code.
    expect($primaries('10470'))->toBe(['my:subdistrict:district:pulau-pinang:timur-laut:tanjong-tokong'])
        ->and($primaries('14200'))->toBe(['my:subdistrict:district:pulau-pinang:seberang-perai-selatan:sungai-jawi']);
});

it('exposes the Selangor book-to-row variant spellings as alternative names', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $names = $provider->areaNames(new AddressCountry);

    expect($names['my:subdistrict:district:selangor:hulu-selangor:bandar-hulu-yam-baharu'])->toContain(
        ['name' => 'Bandar Ulu Yam Baharu', 'name_type' => 'alternative'],
    )->and($names['my:subdistrict:district:selangor:hulu-selangor:bandar-hulu-yam-baharu'])->toContain(
        ['name' => 'Bandar Hulu Yam Baru', 'name_type' => 'alternative'],
    )->and($names['my:subdistrict:district:selangor:kuala-selangor:pekan-kampong-kuantan'])->toContain(
        ['name' => 'Pekan Kampung Kuantan', 'name_type' => 'alternative'],
    )->and($names['my:subdistrict:district:selangor:sabak-bernam:pekan-sabak'])->toContain(
        ['name' => 'Sabak Bernam', 'name_type' => 'common', 'is_preferred' => true],
    );
});

it('places the Selangor town-code primaries on the bandar and pekan rows', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MY', $dir . '/malaysia-postal-codes.csv', $dir . '/malaysia-postal-code-areas.csv', 'aiarmada_addressing_malaysia_v1');

    $byCode = $source->postalCodes()->collect()->groupBy->code;

    $primaries = static fn (string $code): array => $byCode->get($code, collect())
        ->filter(static fn (PostalCodeData $row): bool => $row->isPrimary)
        ->map(static fn (PostalCodeData $row): string => (string) $row->areaSourceId)
        ->all();

    expect($primaries('41000'))->toBe(['my:subdistrict:district:selangor:klang:bandar-klang'])
        ->and($primaries('42200'))->toBe(['my:subdistrict:district:selangor:klang:pekan-kapar'])
        ->and($primaries('42500'))->toBe(['my:subdistrict:district:selangor:kuala-langat:bandar-telok-panglima-garang'])
        ->and($primaries('42600'))->toBe(['my:subdistrict:district:selangor:kuala-langat:jenjarom'])
        ->and($primaries('45200'))->toBe(['my:subdistrict:district:selangor:sabak-bernam:pekan-sabak'])
        ->and($primaries('45100'))->toBe(['my:subdistrict:district:selangor:sabak-bernam:pekan-sungai-air-tawar'])
        ->and($primaries('64000'))->toBe(['my:subdistrict:district:selangor:sepang:bandar-lapangan-terbang-antarabangsa-sepang'])
        ->and($primaries('47000'))->toBe(['my:subdistrict:district:selangor:gombak:bandar-sungai-buloh'])
        ->and($primaries('48100'))->toBe(['my:subdistrict:district:selangor:gombak:batu-arang']);

    // City primaries stay put; cross-state primaries stay out of state.
    expect($primaries('42700'))->toBe(['my:subdistrict:district:selangor:kuala-langat:banting'])
        ->and($primaries('43400'))->toBe(['my:subdistrict:district:selangor:petaling:serdang'])
        ->and($primaries('35900'))->toBe(['my:subdistrict:district:perak:muallim:tanjong-malim']);

    // 42920 resolves on the Pulau Indah town row with Mukim Klang as
    // secondary (postcode.my "42920 Pulau Lumut", Pos-live cell).
    expect($primaries('42920'))->toBe(['my:subdistrict:district:selangor:klang:pulau-indah']);

    $secondaries = static fn (string $code): array => $byCode->get($code, collect())
        ->reject(static fn (PostalCodeData $row): bool => $row->isPrimary)
        ->map(static fn (PostalCodeData $row): string => (string) $row->areaSourceId)
        ->values()
        ->all();

    expect($secondaries('42920'))->toBe(['my:subdistrict:district:selangor:klang:klang']);
});

it('exposes the Pulau Indah town row with its former name', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    $pulauIndah = $areas->get('my:subdistrict:district:selangor:klang:pulau-indah');

    expect($pulauIndah->type)->toBe('bandar')
        ->and($pulauIndah->level)->toBe(3)
        ->and($pulauIndah->parentSourceId)->toBe('my:district:selangor:klang');

    $country = new AddressCountry;
    $roles = $provider->areaRoles($country);

    expect($roles['my:subdistrict:district:selangor:klang:pulau-indah'][0]['role'])->toBe('administrative_subdivision');

    $names = $provider->areaNames($country);

    expect($names['my:subdistrict:district:selangor:klang:pulau-indah'])->toContain(
        ['name' => 'Pulau Lumut', 'name_type' => 'alternative'],
    );

    $links = $provider->areaRelationships($country)['my:subdistrict:district:selangor:klang:pulau-indah'];

    expect($links)->toContain(
        ['parent_source_id' => 'my:district:selangor:klang', 'relationship_type' => 'contains', 'hierarchy_type' => 'administrative'],
        ['parent_source_id' => 'my:state:selangor', 'relationship_type' => 'contains', 'hierarchy_type' => 'administrative'],
    );
});

it('exposes the Terengganu book-to-row postal spellings as alternative names', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $names = $provider->areaNames(new AddressCountry);

    expect($names['my:subdistrict:district:terengganu:kemaman:pekan-air-putih'])->toContain(
        ['name' => 'Ayer Puteh', 'name_type' => 'alternative'],
    )->and($names['my:subdistrict:district:terengganu:marang:pekan-bukit-payung'])->toContain(
        ['name' => 'Bukit Payong', 'name_type' => 'alternative'],
    );
});

it('places the Terengganu town-code primaries on the pekan rows', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MY', $dir . '/malaysia-postal-codes.csv', $dir . '/malaysia-postal-code-areas.csv', 'aiarmada_addressing_malaysia_v1');

    $byCode = $source->postalCodes()->collect()->groupBy->code;

    $primaries = static fn (string $code): array => $byCode->get($code, collect())
        ->filter(static fn (PostalCodeData $row): bool => $row->isPrimary)
        ->map(static fn (PostalCodeData $row): string => (string) $row->areaSourceId)
        ->all();

    expect($primaries('22200'))->toBe(['my:subdistrict:district:terengganu:besut:pekan-kampung-raja'])
        ->and($primaries('22300'))->toBe(['my:subdistrict:district:terengganu:besut:pekan-kuala-besut'])
        ->and($primaries('24200'))->toBe(['my:subdistrict:district:terengganu:kemaman:pekan-kemasik'])
        ->and($primaries('24100'))->toBe(['my:subdistrict:district:terengganu:kemaman:pekan-kijal'])
        ->and($primaries('21700'))->toBe(['my:subdistrict:district:terengganu:hulu-terengganu:pekan-kuala-berang'])
        ->and($primaries('21400'))->toBe(['my:subdistrict:district:terengganu:marang:pekan-bukit-payung'])
        ->and($primaries('24050'))->toBe(['my:subdistrict:district:terengganu:kemaman:pekan-air-putih']);

    // Bandar primaries stay; the Paka postal town keeps its code.
    expect($primaries('24000'))->toBe(['my:subdistrict:district:terengganu:kemaman:cukai'])
        ->and($primaries('23100'))->toBe(['my:subdistrict:district:terengganu:dungun:paka']);
});

it('exposes the WPKL gazetted towns as linkless admin rows under the territory', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    // TIADA DAERAH: towns hang directly under the territory at level 2,
    // and KL postal routing stays on the state + constituencies by design.
    expect($areas->get('my:subdistrict:state:wilayah-persekutuan-kuala-lumpur:bandar-kuala-lumpur')->type)->toBe('bandar')
        ->and($areas->get('my:subdistrict:state:wilayah-persekutuan-kuala-lumpur:bandar-kuala-lumpur')->level)->toBe(2)
        ->and($areas->get('my:subdistrict:state:wilayah-persekutuan-kuala-lumpur:pekan-batu-caves')->type)->toBe('pekan')
        ->and($areas->get('my:subdistrict:state:wilayah-persekutuan-kuala-lumpur:pekan-sungai-besi')->type)->toBe('pekan');

    $names = $provider->areaNames(new AddressCountry);

    expect($names['my:subdistrict:state:wilayah-persekutuan-kuala-lumpur:bandar-bandar-baharu-sungai-besi'])->toContain(
        ['name' => 'Bandar Bandar Baru Sungai Besi', 'name_type' => 'alternative'],
    );
});

it('keeps WPKL postcode primaries on the state and constituencies', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MY', $dir . '/malaysia-postal-codes.csv', $dir . '/malaysia-postal-code-areas.csv', 'aiarmada_addressing_malaysia_v1');

    $byCode = $source->postalCodes()->collect()->groupBy->code;

    $primaries = static fn (string $code): array => $byCode->get($code, collect())
        ->filter(static fn (PostalCodeData $row): bool => $row->isPrimary)
        ->map(static fn (PostalCodeData $row): string => (string) $row->areaSourceId)
        ->all();

    expect($primaries('52100'))->toBe(['my:subdistrict:state:wilayah-persekutuan-kuala-lumpur:kepong'])
        ->and($primaries('56000'))->toBe(['my:subdistrict:state:wilayah-persekutuan-kuala-lumpur:cheras'])
        ->and($primaries('50000'))->toBe(['my:state:wilayah-persekutuan-kuala-lumpur'])
        ->and($primaries('60000'))->toBe(['my:state:wilayah-persekutuan-kuala-lumpur']);
});

it('exposes all 20 Putrajaya precincts under the territory', function (): void {
    $provider = app(MalaysiaGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    $precincts = $areas->filter(
        static fn ($area): bool => $area->parentSourceId === 'my:state:wilayah-persekutuan-putrajaya',
    );

    expect($precincts)->toHaveCount(20)
        ->and($areas->get('my:subdistrict:state:wilayah-persekutuan-putrajaya:precinct-1')->type)->toBe('precinct')
        ->and($areas->get('my:subdistrict:state:wilayah-persekutuan-putrajaya:precinct-20')->type)->toBe('precinct');
});

it('places Putrajaya town-code primaries on the lead precincts', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MY', $dir . '/malaysia-postal-codes.csv', $dir . '/malaysia-postal-code-areas.csv', 'aiarmada_addressing_malaysia_v1');

    $byCode = $source->postalCodes()->collect()->groupBy->code;

    $primaries = static fn (string $code): array => $byCode->get($code, collect())
        ->filter(static fn (PostalCodeData $row): bool => $row->isPrimary)
        ->map(static fn (PostalCodeData $row): string => (string) $row->areaSourceId)
        ->all();

    expect($primaries('62000'))->toBe(['my:subdistrict:state:wilayah-persekutuan-putrajaya:precinct-1'])
        ->and($primaries('62100'))->toBe(['my:subdistrict:state:wilayah-persekutuan-putrajaya:precinct-2'])
        ->and($primaries('62300'))->toBe(['my:subdistrict:state:wilayah-persekutuan-putrajaya:precinct-11'])
        ->and($primaries('62502'))->toBe(['my:state:wilayah-persekutuan-putrajaya']);
});

it('resolves the proven gap postcodes on their postal towns', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MY', $dir . '/malaysia-postal-codes.csv', $dir . '/malaysia-postal-code-areas.csv', 'aiarmada_addressing_malaysia_v1');

    $byCode = $source->postalCodes()->collect()->groupBy->code;

    // Pos Malaysia getStateByPostcode returns "Post Code Not Exist" for
    // these (verified twice, Oct 2026); they stay out of the dataset.
    // 14700, 42425, 42900, 42907 were packaged phantoms: each sits in a
    // fully dead API neighborhood (146xx-148xx, 4242x, 4290x) and was
    // purged Oct 2026. Pulau Indah is 42920; Telok Panglima Garang is
    // 42500/42507/42509.
    foreach (['22564', '27800', '29115', '29452', '29466', '94100', '48500', '56300', '64999', '74300', '14700', '42425', '42900', '42907'] as $code) {
        expect($byCode->has($code))->toBeFalse($code);
    }

    $primaries = static fn (string $code): array => $byCode->get($code, collect())
        ->filter(static fn (PostalCodeData $row): bool => $row->isPrimary)
        ->map(static fn (PostalCodeData $row): string => (string) $row->areaSourceId)
        ->all();

    // Each code below is proven by addressed usage (school, clinic,
    // agency, or business address) or a clean courier listing, on top of
    // a non-400 Pos range check; the primary mirrors the sibling base
    // code for the same postal town. Pos 200s alone prove only a live
    // range, never exact existence.
    expect($primaries('72130'))->toBe(['my:subdistrict:district:negeri-sembilan:jempol:bandar-seri-jempol'])
        ->and($primaries('76470'))->toBe(['my:subdistrict:district:melaka:melaka-tengah:melaka'])
        ->and($primaries('15250'))->toBe(['my:subdistrict:district:kelantan:kota-bharu:kota-bharu'])
        ->and($primaries('26020'))->toBe(['my:subdistrict:district:pahang:kuantan:kuantan'])
        ->and($primaries('41450'))->toBe(['my:subdistrict:district:selangor:klang:bandar-klang'])
        ->and($primaries('42005'))->toBe(['my:subdistrict:district:selangor:klang:port-swettenham'])
        ->and($primaries('46620'))->toBe(['my:subdistrict:district:selangor:petaling:petaling-jaya'])
        ->and($primaries('54080'))->toBe(['my:state:wilayah-persekutuan-kuala-lumpur'])
        ->and($primaries('83740'))->toBe(['my:subdistrict:district:johor:batu-pahat:yong-peng'])
        ->and($primaries('84060'))->toBe(['my:subdistrict:district:johor:muar:bandar'])
        ->and($primaries('85007'))->toBe(['my:subdistrict:district:johor:segamat:segamat'])
        ->and($primaries('86009'))->toBe(['my:subdistrict:district:johor:kluang:bandar-kluang'])
        ->and($primaries('89070'))->toBe(['my:subdistrict:district:sabah:kudat:pekan-kudat'])
        // 11460 (Padang Tembak) takes the George Town primary: no Padang
        // Tembak row exists (250 Jalan Air Itam uses 11460 George Town).
        // 40750/47580/80120 come from Samsung's delivery list, which
        // carries zero known-dead codes. (94111 Tanjung Datu is covered
        // by the Sarawak rectified-postcodes test instead.)
        ->and($primaries('11460'))->toBe(['my:subdistrict:district:pulau-pinang:timur-laut:bandar-george-town'])
        ->and($primaries('40750'))->toBe(['my:subdistrict:district:selangor:petaling:shah-alam'])
        ->and($primaries('46661'))->toBe(['my:subdistrict:district:selangor:petaling:petaling-jaya'])
        ->and($primaries('47580'))->toBe(['my:subdistrict:district:selangor:petaling:subang-jaya'])
        ->and($primaries('58400'))->toBe(['my:state:wilayah-persekutuan-kuala-lumpur'])
        ->and($primaries('59400'))->toBe(['my:state:wilayah-persekutuan-kuala-lumpur'])
        ->and($primaries('80120'))->toBe(['my:subdistrict:district:johor:johor-bahru:johor-bahru'])
        ->and($primaries('81060'))->toBe(['my:subdistrict:district:johor:kulai:bandar-kulai'])
        ->and($primaries('81150'))->toBe(['my:subdistrict:district:johor:johor-bahru:johor-bahru'])
        ->and($primaries('83720'))->toBe(['my:subdistrict:district:johor:batu-pahat:yong-peng']);

    // Excluded as unproven (Oct 2026 re-verification): live Pos range
    // but no addressed usage, clean listing, or directory page found.
    // These are NOT disproven — reinstate with concrete proof only.
    foreach (['34210', '32910', '31958', '32699', '77453', '77483', '20126', '21455', '21710', '22009', '25562', '27101', '27356', '28108', '28293', '28773', '28781', '48040', '63009', '73530', '55555'] as $code) {
        expect($byCode->has($code))->toBeFalse($code);
    }

    // The surviving codes behind the purged phantoms keep resolving
    // (42920 now points at the Bandar Pulau Indah town row).
    expect($primaries('42920'))->toBe(['my:subdistrict:district:selangor:klang:pulau-indah'])
        ->and($primaries('42500'))->toBe(['my:subdistrict:district:selangor:kuala-langat:bandar-telok-panglima-garang'])
        ->and($primaries('42507'))->toBe(['my:subdistrict:district:selangor:kuala-langat:bandar-telok-panglima-garang'])
        ->and($primaries('42509'))->toBe(['my:subdistrict:district:selangor:kuala-langat:bandar-telok-panglima-garang']);

    // 21040 keeps its Kuala Terengganu primary and gains the Marang
    // mukims covering Kampung Temiang, Kampung Jerong Seberang, and
    // Kampung Jerong Tuan (Pengkalan Berangan/Jerung cluster).
    $secondaries = static fn (string $code): array => $byCode->get($code, collect())
        ->reject(static fn (PostalCodeData $row): bool => $row->isPrimary)
        ->map(static fn (PostalCodeData $row): string => (string) $row->areaSourceId)
        ->values()
        ->all();

    expect($primaries('21040'))->toBe(['my:subdistrict:district:terengganu:kuala-terengganu:kuala-terengganu'])
        ->and($secondaries('21040'))->toBe([
            'my:subdistrict:district:terengganu:marang:jerung',
            'my:subdistrict:district:terengganu:marang:bukit-payung',
        ]);
});
