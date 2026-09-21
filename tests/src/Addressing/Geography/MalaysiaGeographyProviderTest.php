<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Contracts\AddressAreaSource;
use AIArmada\Addressing\Contracts\CountryAddressAreaMetadataProvider;
use AIArmada\Addressing\Contracts\CountryGeographyProvider;
use AIArmada\Addressing\Contracts\CountryHierarchyProvider;
use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Geography\Malaysia\MalaysiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRole;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;
use AIArmada\Addressing\Support\ArrayAddressAreaSource;
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
