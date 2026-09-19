<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Bulgaria\BulgariaAddressFormatter;
use AIArmada\Addressing\Geography\Bulgaria\BulgariaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(BulgariaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Bulgarian tree with state links', function (): void {
    $this->seedCountry('BG');

    $result = app(SeedCountryGeographiesAction::class)->execute('BG');
    $country = AddressCountry::query()->where('iso2', 'BG')->firstOrFail();

    expect($result['seeded'])->toContain('BG')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(28)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(28)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(28)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(28);
});

it('formats Bulgarian addresses with the postcode left of the locality', function (): void {
    $formatted = app(BulgariaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'ul. Aleksandur Ekzarkh 2',
        'city' => 'PLOVDIV',
        'postcode' => '4000',
        'country_code' => 'BG',
    ]));

    expect($formatted)->toBe("ul. Aleksandur Ekzarkh 2\n4000 PLOVDIV\nBulgaria");
});
it('formats Bulgarian rural addresses with the province below the postcode line', function (): void {
    $formatted = app(BulgariaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'ul. Trifon Georgiev 1',
        'city' => 'Mechka',
        'state' => 'Ruse',
        'postcode' => '3264',
        'country_code' => 'BG',
    ]));

    expect($formatted)->toBe("ul. Trifon Georgiev 1\n3264 Mechka\nRuse\nBulgaria");
});
