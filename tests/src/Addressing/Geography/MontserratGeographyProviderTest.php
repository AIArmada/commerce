<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Montserrat\MontserratAddressFormatter;
use AIArmada\Addressing\Geography\Montserrat\MontserratGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(MontserratGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('parish')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Montserratian tree with state links', function (): void {
    $this->seedCountry('MS');

    $result = app(SeedCountryGeographiesAction::class)->execute('MS');
    $country = AddressCountry::query()->where('iso2', 'MS')->firstOrFail();

    expect($result['seeded'])->toContain('MS')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'parish')->count())->toBe(3)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(3);
});

it('formats Montserratian addresses with the postcode right of the locality', function (): void {
    $formatted = app(MontserratAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 140',
        'city' => 'Brades',
        'postcode' => 'MSR1110',
        'country_code' => 'MS',
    ]));

    expect($formatted)->toBe("PO Box 140\nBrades, MSR1110\nMontserrat");
});
it('formats Montserratian Olveston addresses with the district postcode', function (): void {
    $formatted = app(MontserratAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 12',
        'city' => 'Olveston',
        'postcode' => 'MSR1350',
        'country_code' => 'MS',
    ]));

    expect($formatted)->toBe("PO Box 12\nOlveston, MSR1350\nMontserrat");
});
