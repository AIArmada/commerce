<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Slovakia\SlovakiaAddressFormatter;
use AIArmada\Addressing\Geography\Slovakia\SlovakiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SlovakiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Slovak tree with state links', function (): void {
    $this->seedCountry('SK');

    $result = app(SeedCountryGeographiesAction::class)->execute('SK');
    $country = AddressCountry::query()->where('iso2', 'SK')->firstOrFail();

    expect($result['seeded'])->toContain('SK')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(8)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(8)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(8)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(8);
});

it('formats Slovak addresses with the spaced postcode left of the office', function (): void {
    $formatted = app(SlovakiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Lúčna 1157/13',
        'city' => 'TRNAVA',
        'postcode' => '917 01',
        'country_code' => 'SK',
    ]));

    expect($formatted)->toBe("Lúčna 1157/13\n917 01 TRNAVA\nSlovakia");
});
it('formats Slovak Žilina addresses with the town postcode', function (): void {
    $formatted = app(SlovakiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Národná 5',
        'city' => 'Žilina',
        'postcode' => '010 01',
        'country_code' => 'SK',
    ]));

    expect($formatted)->toBe("Národná 5\n010 01 Žilina\nSlovakia");
});
