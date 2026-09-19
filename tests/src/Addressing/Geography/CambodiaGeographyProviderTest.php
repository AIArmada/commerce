<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Cambodia\CambodiaAddressFormatter;
use AIArmada\Addressing\Geography\Cambodia\CambodiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaName;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(CambodiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Cambodian tree with state links', function (): void {
    $this->seedCountry('KH');

    $result = app(SeedCountryGeographiesAction::class)->execute('KH');
    $country = AddressCountry::query()->where('iso2', 'KH')->firstOrFail();

    expect($result['seeded'])->toContain('KH')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(25)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(25)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(24)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(25);

    $area = AddressArea::query()->where('source_id', 'kh:province:preah-sihanouk')->firstOrFail();

    expect(AddressAreaName::query()
        ->where('address_area_id', $area->getKey())
        ->where('name', 'Sihanoukville')
        ->exists())->toBeTrue();
});

it('formats Cambodian addresses with the postcode right of the province', function (): void {
    $formatted = app(CambodiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '100E0 Street 118',
        'state' => 'PHNOM PENH',
        'postcode' => '120209',
        'country_code' => 'KH',
    ]));

    expect($formatted)->toBe("100E0 Street 118\nPHNOM PENH 120209\nCambodia");
});
it('formats Cambodian addresses with the city above the postcode province line', function (): void {
    $formatted = app(CambodiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '100E0 Street 118',
        'city' => 'Krong Siem Reap',
        'state' => 'Siem Reap',
        'postcode' => '17000',
        'country_code' => 'KH',
    ]));

    expect($formatted)->toBe("100E0 Street 118\nKrong Siem Reap\nSiem Reap 17000\nCambodia");
});
