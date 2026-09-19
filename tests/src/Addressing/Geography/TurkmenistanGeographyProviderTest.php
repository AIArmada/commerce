<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Turkmenistan\TurkmenistanAddressFormatter;
use AIArmada\Addressing\Geography\Turkmenistan\TurkmenistanGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(TurkmenistanGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Turkmen tree with state links', function (): void {
    $this->seedCountry('TM');

    $result = app(SeedCountryGeographiesAction::class)->execute('TM');
    $country = AddressCountry::query()->where('iso2', 'TM')->firstOrFail();

    expect($result['seeded'])->toContain('TM')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(6);
});

it('formats Turkmen addresses with the postcode below the locality', function (): void {
    $formatted = app(TurkmenistanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'j. 19 otag 1',
        'line2' => 'kv. 122',
        'city' => 'BALKANABAT',
        'state' => 'Balkan',
        'postcode' => '745100',
        'country_code' => 'TM',
    ]));

    expect($formatted)->toBe("j. 19 otag 1\nkv. 122\nBALKANABAT\nBalkan\n745100\nTurkmenistan");
});
it('prints matching Turkmen city and capital once above the postcode', function (): void {
    $formatted = app(TurkmenistanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Galkynysh Street 1',
        'city' => 'ASHGABAT',
        'state' => 'Ashgabat',
        'postcode' => '744000',
        'country_code' => 'TM',
    ]));

    expect($formatted)->toBe("Galkynysh Street 1\nASHGABAT\n744000\nTurkmenistan");
});
