<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Malta\MaltaAddressFormatter;
use AIArmada\Addressing\Geography\Malta\MaltaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(MaltaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('local_council')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Maltese tree with state links', function (): void {
    $this->seedCountry('MT');

    $result = app(SeedCountryGeographiesAction::class)->execute('MT');
    $country = AddressCountry::query()->where('iso2', 'MT')->firstOrFail();

    expect($result['seeded'])->toContain('MT')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(68)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(68)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'local_council')->count())->toBe(68)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(68);
});

it('formats Maltese addresses with the postcode below the locality', function (): void {
    $formatted = app(MaltaAddressFormatter::class)->format(AddressData::from([
        'line1' => '38 Triq it-Tempji Neolitici',
        'city' => 'IL-HAMRUN',
        'postcode' => 'HMR 1428',
        'country_code' => 'MT',
    ]));

    expect($formatted)->toBe("38 Triq it-Tempji Neolitici\nIL-HAMRUN\nHMR 1428\nMalta");
});
it('formats Maltese Valletta addresses with the locality postcode', function (): void {
    $formatted = app(MaltaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Palace Square 1',
        'city' => 'VALLETTA',
        'postcode' => 'VLT 1117',
        'country_code' => 'MT',
    ]));

    expect($formatted)->toBe("Palace Square 1\nVALLETTA\nVLT 1117\nMalta");
});
