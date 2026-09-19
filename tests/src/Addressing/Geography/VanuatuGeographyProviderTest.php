<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Vanuatu\VanuatuAddressFormatter;
use AIArmada\Addressing\Geography\Vanuatu\VanuatuGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(VanuatuGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Vanuatuan tree with state links', function (): void {
    $this->seedCountry('VU');

    $result = app(SeedCountryGeographiesAction::class)->execute('VU');
    $country = AddressCountry::query()->where('iso2', 'VU')->firstOrFail();

    expect($result['seeded'])->toContain('VU')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(6)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(6);
});

it('formats Vanuatu addresses without a postcode system', function (): void {
    $formatted = app(VanuatuAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Port Vila',
        'country_code' => 'VU',
    ]));

    expect($formatted)->toBe("PO Box 1\nPort Vila\nVanuatu");
});

it('prints any supplied Port Vila code on its own line', function (): void {
    $formatted = app(VanuatuAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Port Vila',
        'postcode' => '99999',
        'country_code' => 'VU',
    ]));

    expect($formatted)->toBe("PO Box 1\nPort Vila\n99999\nVanuatu");
});
