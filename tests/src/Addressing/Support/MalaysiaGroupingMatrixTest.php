<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedAddressStatesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;
use AIArmada\Addressing\Support\CountryAddressProfileResolver;

it('groups subdivision and locality correctly for every Malaysian state and WP', function (): void {
    app(SeedAddressCountriesAction::class)->execute();
    app(SeedAddressStatesAction::class)->execute();

    app(SeedCountryGeographiesAction::class)->execute('MY');

    // [subdivision gate, locality gate, grouped, subdivision label, locality label]
    $expected = [
        'Johor' => ['district', 'district', true, 'Mukim / Bandar / Pekan', 'Locality'],
        'Kedah' => ['district', 'district', true, 'Mukim / Bandar / Pekan', 'Locality'],
        'Kelantan' => ['district', 'district', true, 'Mukim / Bandar / Pekan', 'Locality'],
        'Melaka' => ['district', 'district', true, 'Mukim / Bandar / Pekan', 'Locality'],
        'Negeri Sembilan' => ['district', 'district', true, 'Mukim / Bandar / Pekan', 'Locality'],
        'Pahang' => ['district', 'district', true, 'Mukim / Bandar / Pekan', 'Locality'],
        'Perak' => ['district', 'district', true, 'Mukim / Bandar / Pekan', 'Locality'],
        'Perlis' => ['region', 'region', true, 'Mukim', 'Locality'],
        'Pulau Pinang' => ['district', 'district', true, 'Mukim / Bandar', 'Locality'],
        'Sabah' => ['district', 'district', true, 'Subdistrict', 'Locality'],
        // Sarawak has no locality rows (all towns are level-4
        // subdistricts), so the locality gate falls back to the region
        // and the pair stays ungrouped.
        'Sarawak' => ['district', 'region', false, 'Subdistrict', 'Locality / Precinct / Kampung'],
        'Selangor' => ['district', 'district', true, 'Mukim / Bandar / Pekan', 'Locality'],
        'Terengganu' => ['district', 'district', true, 'Mukim / Bandar / Pekan', 'Locality'],
        'WP Kuala Lumpur' => ['region', 'region', true, 'Mukim', 'Locality'],
        // Labuan and Putrajaya have no subdivision rows (generic fallback
        // label); consumers render the locality control alone there.
        'WP Labuan' => ['region', 'region', true, 'Mukim / Subdistrict / Bandar / Pekan', 'Locality'],
        'WP Putrajaya' => ['region', 'region', true, 'Mukim / Subdistrict / Bandar / Pekan', 'Precinct'],
    ];

    $resolver = app(CountryAddressProfileResolver::class);
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $states = State::query()->where('country_id', $country->getKey())->orderBy('name')->get();

    expect($states->pluck('name')->all())->toEqualCanonicalizing(array_keys($expected));

    foreach ($states as $state) {
        $id = (string) $state->getKey();
        [$subGate, $locGate, $grouped, $subLabel, $locLabel] = $expected[$state->name];

        expect($resolver->effectiveParentLevel('MY', 'administrative_subdivision', $id)?->key)
            ->toBe($subGate, "subdivision gate for {$state->name}")
            ->and($resolver->effectiveParentLevel('MY', 'postal_locality', $id)?->key)
            ->toBe($locGate, "locality gate for {$state->name}")
            ->and($resolver->shouldGroupSubdivisionLocality('MY', $id))
            ->toBe($grouped, "grouping for {$state->name}")
            ->and($resolver->levelLabel('MY', 'administrative_subdivision', $id))
            ->toBe($subLabel, "subdivision label for {$state->name}")
            ->and($resolver->levelLabel('MY', 'postal_locality', $id))
            ->toBe($locLabel, "locality label for {$state->name}");
    }
});
