<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Greenland\GreenlandAddressFormatter;
use AIArmada\Addressing\Geography\Greenland\GreenlandGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(GreenlandGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('municipality')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Greenlandic tree with state links', function (): void {
    $this->seedCountry('GL');

    $result = app(SeedCountryGeographiesAction::class)->execute('GL');
    $country = AddressCountry::query()->where('iso2', 'GL')->firstOrFail();

    expect($result['seeded'])->toContain('GL')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(5)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(5);
});

it('formats Greenlandic addresses with the code left of the locality', function (): void {
    $formatted = app(GreenlandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Aqqusinersuaq 10',
        'city' => 'Nuuk',
        'postcode' => '3900',
        'country_code' => 'GL',
    ]));

    expect($formatted)->toBe("Aqqusinersuaq 10\n3900 Nuuk\nGreenland");
});

it('formats Ilulissat addresses with their own code', function (): void {
    $formatted = app(GreenlandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 9',
        'city' => 'Ilulissat',
        'postcode' => '3952',
        'country_code' => 'GL',
    ]));

    expect($formatted)->toBe("PO Box 9\n3952 Ilulissat\nGreenland");
});
