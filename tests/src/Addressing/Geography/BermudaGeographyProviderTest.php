<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Bermuda\BermudaAddressFormatter;
use AIArmada\Addressing\Geography\Bermuda\BermudaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(BermudaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('municipality')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Bermudian tree with state links', function (): void {
    $this->seedCountry('BM');

    $result = app(SeedCountryGeographiesAction::class)->execute('BM');
    $country = AddressCountry::query()->where('iso2', 'BM')->firstOrFail();

    expect($result['seeded'])->toContain('BM')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(9)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(9);
});

it('formats Bermudian street addresses with the postcode right of the parish', function (): void {
    $formatted = app(BermudaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Upper Apt # 1',
        'line2' => '9 Leafy Lane',
        'state' => "SMITH'S",
        'postcode' => 'FL 07',
        'country_code' => 'BM',
    ]));

    expect($formatted)->toBe("Upper Apt # 1\n9 Leafy Lane\nSMITH'S FL 07\nBermuda");
});
it('formats Bermudian box addresses with the letter postcode', function (): void {
    $formatted = app(BermudaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box HM 2469',
        'city' => 'HAMILTON',
        'postcode' => 'HM GX',
        'country_code' => 'BM',
    ]));

    expect($formatted)->toBe("PO Box HM 2469\nHAMILTON HM GX\nBermuda");
});
