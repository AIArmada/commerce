<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\AntiguaAndBarbuda\AntiguaAndBarbudaAddressFormatter;
use AIArmada\Addressing\Geography\AntiguaAndBarbuda\AntiguaAndBarbudaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(AntiguaAndBarbudaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('parish')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Antiguan and Barbudan tree with state links', function (): void {
    $this->seedCountry('AG');

    $result = app(SeedCountryGeographiesAction::class)->execute('AG');
    $country = AddressCountry::query()->where('iso2', 'AG')->firstOrFail();

    expect($result['seeded'])->toContain('AG')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(8)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(8)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'parish')->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'dependency')->count())->toBe(2)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(8);
});

it('formats Antiguan addresses without a postcode system', function (): void {
    $formatted = app(AntiguaAndBarbudaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 123',
        'city' => "ST. JOHN'S",
        'country_code' => 'AG',
    ]));

    expect($formatted)->toBe("P.O. Box 123\nST. JOHN'S\nAntigua and Barbuda");
});
it('prints any supplied Antiguan code on its own line', function (): void {
    $formatted = app(AntiguaAndBarbudaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 123',
        'city' => "ST. JOHN'S",
        'postcode' => '99999',
        'country_code' => 'AG',
    ]));

    expect($formatted)->toBe("P.O. Box 123\nST. JOHN'S\n99999\nAntigua and Barbuda");
});
