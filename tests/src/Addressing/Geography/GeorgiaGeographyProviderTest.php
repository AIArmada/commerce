<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Georgia\GeorgiaAddressFormatter;
use AIArmada\Addressing\Geography\Georgia\GeorgiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(GeorgiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Georgian tree with state links', function (): void {
    $this->seedCountry('GE');

    $result = app(SeedCountryGeographiesAction::class)->execute('GE');
    $country = AddressCountry::query()->where('iso2', 'GE')->firstOrFail();

    expect($result['seeded'])->toContain('GE')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(12)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(12)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'autonomous_republic')->count())->toBe(2)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(12);
});

it('formats Georgian addresses with the postcode left of the locality', function (): void {
    $formatted = app(GeorgiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Pekinia ave. #39, flat 66',
        'city' => 'TBILISI',
        'postcode' => '0160',
        'country_code' => 'GE',
    ]));

    expect($formatted)->toBe("Pekinia ave. #39, flat 66\n0160 TBILISI\nGeorgia");
});
it('formats rural Georgian addresses with the region below the postcode line', function (): void {
    $formatted = app(GeorgiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Tavisupleba Street 5',
        'city' => 'Telavi',
        'state' => 'Kakheti',
        'postcode' => '2200',
        'country_code' => 'GE',
    ]));

    expect($formatted)->toBe("Tavisupleba Street 5\n2200 Telavi\nKakheti\nGeorgia");
});
