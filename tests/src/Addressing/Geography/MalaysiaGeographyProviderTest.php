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

function malaysiaMainCsvRows(): array
{
    $providerFile = (string) (new ReflectionClass(MalaysiaGeographyProvider::class))->getFileName();
    $lines = file(dirname($providerFile, 4) . '/resources/geography/malaysia-address-areas.csv');
    $header = str_getcsv((string) array_shift($lines));

    return array_map(static fn (string $line): array => array_combine($header, str_getcsv($line)), $lines);
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
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(16)
        ->and(State::query()->where('country_id', $country->id)->where('code', '16')->exists())->toBeTrue();
});

it('provides all sixteen Malaysian state and federal territory mappings', function (): void {
    $mappings = app(MalaysiaGeographyProvider::class)->stateAreaMappings();
    $mappingCodes = array_map(
        static fn (string | int $code): string => mb_str_pad((string) $code, 2, '0', STR_PAD_LEFT),
        array_keys($mappings),
    );

    expect($mappings)->toHaveCount(16)
        ->and($mappingCodes)->toBe([
            '01', '02', '03', '04', '05', '06', '07', '08',
            '09', '10', '11', '12', '13', '14', '15', '16',
        ]);
});

it('defines separate postal and administrative hierarchies with a shared first-level region', function (): void {
    $hierarchies = app(MalaysiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(2)
        ->and($hierarchies[0]->key)->toBe('postal')
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->label)->toBe('State / Federal Territory')
        ->and($hierarchies[0]->levels[1]->key)->toBe('locality')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('region')
        ->and($hierarchies[1]->key)->toBe('administrative')
        ->and($hierarchies[1]->levels[0]->key)->toBe('region')
        ->and($hierarchies[1]->levels[1]->key)->toBe('division')
        ->and($hierarchies[1]->levels[1]->parentKey)->toBe('region')
        ->and($hierarchies[1]->levels[2]->key)->toBe('district')
        ->and($hierarchies[1]->levels[2]->parentKey)->toBe('region')
        ->and($hierarchies[1]->levels[3]->key)->toBe('subdivision')
        ->and($hierarchies[1]->levels[3]->parentKey)->toBe('region');
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

it('maps the bundled state, district, and locality rows', function (): void {
    $rows = malaysiaMainCsvRows();
    $byType = array_count_values(array_column($rows, 'type'));

    expect($rows)->toHaveCount(1848)
        ->and($byType['state'] ?? 0)->toBe(13)
        ->and($byType['wilayah_persekutuan'] ?? 0)->toBe(3)
        ->and($byType['division'] ?? 0)->toBe(17)
        ->and($byType['district'] ?? 0)->toBe(160)
        ->and($byType['minor_district'] ?? 0)->toBe(1)
        ->and($byType['mukim'] ?? 0)->toBe(1283)
        ->and($byType['subdistrict'] ?? 0)->toBe(312)
        ->and($byType['locality'] ?? 0)->toBe(39)
        ->and($byType['precinct'] ?? 0)->toBe(20);

    $byId = array_column($rows, null, 'source_id');
    $orphans = [];

    foreach ($rows as $row) {
        if ($row['parent_source_id'] !== '' && ! isset($byId[$row['parent_source_id']])) {
            $orphans[] = $row['source_id'];
        }
    }

    expect($orphans)->toBe([]);

    expect($byId['my:state:johor']['name'])->toBe('Johor')
        ->and($byId['my:state:wilayah-persekutuan-kuala-lumpur']['type'])->toBe('wilayah_persekutuan')
        ->and($byId['my:subdistrict:state:wilayah-persekutuan-kuala-lumpur:wangsa-maju']['name'])->toBe('Wangsa Maju')
        ->and($byId['my:subdistrict:state:wilayah-persekutuan-kuala-lumpur:wangsa-maju']['type'])->toBe('locality')
        ->and($byId['my:subdistrict:state:wilayah-persekutuan-kuala-lumpur:wangsa-maju']['parent_source_id'])->toBe('my:state:wilayah-persekutuan-kuala-lumpur');

    $first = app(MalaysiaGeographyProvider::class)->addressAreaSource()->areas()->first();

    expect($first->sourceId)->toBe('my:state:johor')
        ->and($first->name)->toBe('Johor')
        ->and($first->code)->toBe('johor')
        ->and($first->level)->toBe(1)
        ->and($first->parentSourceId)->toBeNull();
});

it('exposes aliases, roles, and postal versus administrative relationships', function (): void {
    $country = $this->seedCountry('MY');
    $provider = app(MalaysiaGeographyProvider::class);

    $names = $provider->areaNames($country);

    expect($names['my:state:wilayah-persekutuan-kuala-lumpur'] ?? [])->toBe([
        ['name' => 'Kuala Lumpur', 'name_type' => 'common', 'is_preferred' => true],
        ['name' => 'KL', 'name_type' => 'abbreviation'],
    ])->and($names['my:subdistrict:state:wilayah-persekutuan-kuala-lumpur:mukim-hulu-klang'] ?? [])->toBe([
        ['name' => 'Hulu Kelang', 'name_type' => 'alternative'],
    ]);

    $roles = $provider->areaRoles($country);
    $localityId = 'my:subdistrict:state:wilayah-persekutuan-kuala-lumpur:wangsa-maju';

    expect($roles[$localityId] ?? [])->toBe([
        ['role' => 'postal_locality', 'country_code' => 'MY', 'is_primary' => true],
    ]);

    $relationships = $provider->areaRelationships($country);

    expect($relationships[$localityId] ?? [])->toBe([[
        'parent_source_id' => 'my:state:wilayah-persekutuan-kuala-lumpur',
        'relationship_type' => 'contains',
        'hierarchy_type' => 'postal',
    ]])->and($relationships['my:subdistrict:district:johor:johor-bahru:bandar-johor-bahru'] ?? [])->toBe([
        [
            'parent_source_id' => 'my:district:johor:johor-bahru',
            'relationship_type' => 'contains',
            'hierarchy_type' => 'administrative',
        ],
        [
            'parent_source_id' => 'my:state:johor',
            'relationship_type' => 'contains',
            'hierarchy_type' => 'administrative',
        ],
    ]);
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

it('treats the Gombak and Kuala Lumpur Setapak mukims as separate single-parent rows', function (): void {
    $country = $this->seedCountry('MY');
    $provider = app(MalaysiaGeographyProvider::class);

    $sourceIds = $provider->addressAreaSource()->areas()
        ->map(static fn (AddressAreaData $area): string => $area->sourceId)
        ->all();

    expect($sourceIds)->toContain('my:subdistrict:district:selangor:gombak:setapak')
        ->and($sourceIds)->toContain('my:subdistrict:state:wilayah-persekutuan-kuala-lumpur:mukim-setapak')
        ->and($sourceIds)->not->toContain('my:subdistrict:district:selangor:gombak:ulu-kelang');

    $relationships = $provider->areaRelationships($country);

    expect(array_column(
        $relationships['my:subdistrict:district:selangor:gombak:setapak'] ?? [],
        'parent_source_id'
    ))->toBe(['my:district:selangor:gombak', 'my:state:selangor'])
        ->and($relationships['my:subdistrict:state:wilayah-persekutuan-kuala-lumpur:mukim-setapak'] ?? [])->toBe([[
            'parent_source_id' => 'my:state:wilayah-persekutuan-kuala-lumpur',
            'relationship_type' => 'contains',
            'hierarchy_type' => 'administrative',
        ]]);
});

it('seeds the seven gazetted Kuala Lumpur mukims under the Federal Territory', function (): void {
    $this->seedCountry('MY');

    app(SeedCountryGeographiesAction::class)->execute('MY');

    $kl = AddressArea::query()
        ->where('source_id', 'my:state:wilayah-persekutuan-kuala-lumpur')
        ->firstOrFail();

    $mukims = AddressArea::query()
        ->where('parent_id', $kl->getKey())
        ->where('type', 'mukim')
        ->orderBy('name')
        ->pluck('name')
        ->all();

    expect($mukims)->toBe(['Ampang', 'Batu', 'Cheras', 'Hulu Klang', 'Kuala Lumpur', 'Petaling', 'Setapak']);
});

it('resolves every emitted relationship to a bundled area', function (): void {
    $country = $this->seedCountry('MY');
    $provider = app(MalaysiaGeographyProvider::class);

    $known = $provider->addressAreaSource()->areas()
        ->map(static fn (AddressAreaData $area): string => $area->sourceId)
        ->flip()
        ->all();

    $dangling = [];

    foreach ($provider->areaRelationships($country) as $childSourceId => $relationships) {
        if (! isset($known[$childSourceId])) {
            $dangling[] = $childSourceId;
        }

        foreach ($relationships as $relationship) {
            if (! isset($known[$relationship['parent_source_id']])) {
                $dangling[] = $childSourceId . ' -> ' . $relationship['parent_source_id'];
            }
        }
    }

    expect($dangling)->toBe([]);
});

it('keeps single-district mukims on one administrative parent', function (): void {
    $country = $this->seedCountry('MY');

    $relationships = app(MalaysiaGeographyProvider::class)->areaRelationships($country);

    $ampangParents = array_column(
        $relationships['my:subdistrict:district:selangor:hulu-langat:ampang'] ?? [],
        'parent_source_id'
    );
    $batuParents = array_column(
        $relationships['my:subdistrict:district:selangor:gombak:batu'] ?? [],
        'parent_source_id'
    );
    $setapakParents = array_column(
        $relationships['my:subdistrict:district:selangor:gombak:setapak'] ?? [],
        'parent_source_id'
    );

    expect($ampangParents)->not->toContain('my:district:selangor:gombak')
        ->and($batuParents)->not->toContain('my:state:wilayah-persekutuan-kuala-lumpur')
        ->and($setapakParents)->not->toContain('my:state:wilayah-persekutuan-kuala-lumpur');
});
