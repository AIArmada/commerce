<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressStatesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Contracts\AddressAreaSource;
use AIArmada\Addressing\Contracts\CountryAddressProfile;
use AIArmada\Addressing\Contracts\CountryHierarchyProvider;
use AIArmada\Addressing\Data\AddressHierarchyDefinition;
use AIArmada\Addressing\Data\AddressLevelDefinition;
use AIArmada\Addressing\Geography\Lithuania\LithuaniaGeographyProvider;
use AIArmada\Addressing\Geography\UnitedKingdom\UnitedKingdomGeographyProvider;
use AIArmada\Addressing\Models\State;
use AIArmada\Addressing\Support\AddressAreaStateBridge;
use AIArmada\Addressing\Support\ArrayAddressAreaSource;
use AIArmada\Addressing\Support\CountryAddressProfileResolver;

it('lists only the four mapped nations for GB after a full seed', function (): void {
    $country = $this->seedCountry('GB');

    $states = array_values(array_filter(
        json_decode((string) file_get_contents(__DIR__ . '/../../../../packages/addressing/resources/data/states.json'), true),
        static fn (array $row): bool => ($row['country_code'] ?? null) === 'GB',
    ));

    app(SeedAddressStatesAction::class)->execute($states);
    app(UnitedKingdomGeographyProvider::class)->seed($country);
    app(SeedCountryGeographiesAction::class)->execute('GB');

    $resolver = app(CountryAddressProfileResolver::class);

    expect(State::query()->where('country_id', $country->getKey())->count())->toBe(221)
        ->and($resolver->stateSelectionCodes('GB'))->toEqualCanonicalizing(['ENG', 'NIR', 'SCT', 'WLS']);

    $options = $resolver->stateOptionsQuery('GB')?->pluck('code', 'name')->all() ?? [];

    expect($options)->toHaveCount(4)
        ->and(array_values($options))->toEqualCanonicalizing(['ENG', 'NIR', 'SCT', 'WLS']);

    // Every filtered option resolves to an area parent; an unmapped county
    // row stays in the database for City references but offers no parent.
    foreach (array_keys($options) as $name) {
        $stateId = $resolver->stateOptionsQuery('GB')?->where('name', $name)->value('id');

        expect(AddressAreaStateBridge::areaIdForState((string) $stateId, 'administrative'))->not->toBeNull();
    }

    $unmapped = State::query()->where('country_id', $country->getKey())->where('code', 'ABE')->firstOrFail();

    expect(AddressAreaStateBridge::areaIdForState((string) $unmapped->getKey(), 'administrative'))->toBeNull()
        ->and($unmapped->exists)->toBeTrue();
});

it('lists only the ten mapped counties for LT after a full seed', function (): void {
    $country = $this->seedCountry('LT');

    $states = array_values(array_filter(
        json_decode((string) file_get_contents(__DIR__ . '/../../../../packages/addressing/resources/data/states.json'), true),
        static fn (array $row): bool => ($row['country_code'] ?? null) === 'LT',
    ));

    app(SeedAddressStatesAction::class)->execute($states);
    app(LithuaniaGeographyProvider::class)->seed($country);
    app(SeedCountryGeographiesAction::class)->execute('LT');

    $resolver = app(CountryAddressProfileResolver::class);
    $expected = ['AL', 'KU', 'KL', 'MR', 'PN', 'SA', 'TA', 'TE', 'UT', 'VL'];

    expect(State::query()->where('country_id', $country->getKey())->count())->toBe(70)
        ->and($resolver->stateSelectionCodes('LT'))->toEqualCanonicalizing($expected);

    $options = $resolver->stateOptionsQuery('LT')?->pluck('code')->all() ?? [];

    expect($options)->toEqualCanonicalizing($expected);

    foreach ($resolver->stateOptionsQuery('LT')?->pluck('id')->all() ?? [] as $stateId) {
        expect(AddressAreaStateBridge::areaIdForState((string) $stateId, 'administrative'))->not->toBeNull();
    }

    // A lower-tier municipality row remains for City references.
    expect(State::query()->where('country_id', $country->getKey())->where('code', '01')->exists())->toBeTrue();
});

it('falls back to all states for provider-less countries', function (): void {
    $country = $this->seedCountry('MO');

    State::query()->create(['country_id' => $country->getKey(), 'country_code' => 'MO', 'code' => 'MO-A', 'name' => 'Macao A']);
    State::query()->create(['country_id' => $country->getKey(), 'country_code' => 'MO', 'code' => 'MO-B', 'name' => 'Macao B']);

    $resolver = app(CountryAddressProfileResolver::class);

    // Macao has no bundled provider; no filtering applies.
    expect($resolver->stateSelectionCodes('MO'))->toBeNull()
        ->and($resolver->stateOptionsQuery('MO')?->pluck('code')->all())->toEqualCanonicalizing(['MO-A', 'MO-B'])
        ->and($resolver->stateOptionsQuery('XX'))->toBeNull();
});

it('respects the configured state subclass and its global scopes', function (): void {
    $country = $this->seedCountry('GB');

    $states = array_values(array_filter(
        json_decode((string) file_get_contents(__DIR__ . '/../../../../packages/addressing/resources/data/states.json'), true),
        static fn (array $row): bool => ($row['country_code'] ?? null) === 'GB',
    ));

    app(SeedAddressStatesAction::class)->execute($states);
    app(UnitedKingdomGeographyProvider::class)->seed($country);

    $scopedState = new class extends State
    {
        protected static function booted(): void
        {
            parent::booted();

            static::addGlobalScope('test-hide-england', fn ($query) => $query->where('code', '!=', 'ENG'));
        }
    };

    config()->set('addressing.models.state', $scopedState::class);

    $resolver = app(CountryAddressProfileResolver::class);
    $query = $resolver->stateOptionsQuery('GB');

    expect($query)->not->toBeNull()
        ->and($query->pluck('code')->all())->toEqualCanonicalizing(['NIR', 'SCT', 'WLS'])
        ->and($query->first())->toBeInstanceOf($scopedState::class);
});

it('excludes mapping area codes and stringifies numeric state keys', function (): void {
    $profile = new class implements CountryAddressProfile, CountryHierarchyProvider
    {
        public function countryCode(): string
        {
            return 'MY';
        }

        public function addressHierarchies(): array
        {
            return [new AddressHierarchyDefinition('geo', 'Geo', [
                new AddressLevelDefinition(key: 'state', label: 'State', kind: 'state'),
            ])];
        }

        public function addressAreaSource(): AddressAreaSource
        {
            return new ArrayAddressAreaSource('test', []);
        }

        public function stateAreaMappings(): array
        {
            return [
                'ROOT' => ['area_code' => 'LOWER', 'source' => 'test', 'area_level' => 1],
                13 => ['area_code' => 'X', 'source' => 'test', 'area_level' => 1],
            ];
        }
    };
    config()->set('addressing.geography.providers', [get_class($profile)]);

    $country = $this->seedCountry('MY');

    State::query()->create(['country_id' => $country->getKey(), 'country_code' => 'MY', 'code' => 'ROOT', 'name' => 'Root State']);
    State::query()->create(['country_id' => $country->getKey(), 'country_code' => 'MY', 'code' => 'LOWER', 'name' => 'Lower Tier']);
    State::query()->create(['country_id' => $country->getKey(), 'country_code' => 'MY', 'code' => '13', 'name' => 'Numeric State']);

    $resolver = app(CountryAddressProfileResolver::class);

    // Area codes are a different namespace: a lower-tier LOWER row must not
    // become selectable just because ROOT maps to it. Numeric int keys
    // stringify to their state codes.
    expect($resolver->stateSelectionCodes('MY'))->toEqualCanonicalizing(['ROOT', '13'])
        ->and($resolver->stateOptionsQuery('MY')?->pluck('code')->all())->toEqualCanonicalizing(['ROOT', '13']);
});
