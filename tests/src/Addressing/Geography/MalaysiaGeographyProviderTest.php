<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
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
use Illuminate\Support\Str;

it('preserves globally seeded states and adds missing Malaysian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
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
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    app(MalaysiaGeographyProvider::class)->seed($country);
    $state = State::query()->where('country_id', $country->id)->where('code', '01')->firstOrFail();
    $oldArea = AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'type' => 'state',
        'level' => 1,
        'name' => 'Old Johor',
        'slug' => 'old-johor',
        'source' => 'malaysia-provider-v1',
        'source_id' => Str::uuid()->toString(),
    ]);
    $oldLink = AddressAreaStateLink::query()->create([
        'address_area_id' => $oldArea->id,
        'state_id' => $state->id,
        'metadata' => ['provider' => app(MalaysiaGeographyProvider::class)->providerKey()],
    ]);

    app(SeedCountryGeographiesAction::class)->execute('MY');

    expect(AddressAreaStateLink::query()->whereKey($oldLink->id)->exists())->toBeFalse();
});

it('deactivates prior areas when a provider changes its imported source key', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

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
