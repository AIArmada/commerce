<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Bhutan\BhutanAddressFormatter;
use AIArmada\Addressing\Geography\Bhutan\BhutanGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(BhutanGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Bhutanese tree with state links', function (): void {
    $this->seedCountry('BT');

    $result = app(SeedCountryGeographiesAction::class)->execute('BT');
    $country = AddressCountry::query()->where('iso2', 'BT')->firstOrFail();

    expect($result['seeded'])->toContain('BT')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(20)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(20)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(20)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(20);
});

it('formats Bhutanese addresses with the postcode right of the locality', function (): void {
    $formatted = app(BhutanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Chang Lam, JD House',
        'line2' => 'Flat No. 2B',
        'city' => 'Thimphu',
        'state' => 'Thimphu',
        'postcode' => '11001',
        'country_code' => 'BT',
    ]));

    expect($formatted)->toBe("Chang Lam, JD House\nFlat No. 2B\nThimphu 11001\nBhutan");
});
it('prints matching Bhutanese city and dzongkhag once when the postcode is missing', function (): void {
    $formatted = app(BhutanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Chang Lam, JD House',
        'city' => 'Thimphu',
        'state' => 'Thimphu',
        'country_code' => 'BT',
    ]));

    expect($formatted)->toBe("Chang Lam, JD House\nThimphu\nBhutan");
});
