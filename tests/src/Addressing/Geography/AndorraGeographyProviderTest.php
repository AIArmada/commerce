<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Andorra\AndorraAddressFormatter;
use AIArmada\Addressing\Geography\Andorra\AndorraGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(AndorraGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('parish')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Andorran tree with state links', function (): void {
    $this->seedCountry('AD');

    $result = app(SeedCountryGeographiesAction::class)->execute('AD');
    $country = AddressCountry::query()->where('iso2', 'AD')->firstOrFail();

    expect($result['seeded'])->toContain('AD')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'parish')->count())->toBe(7)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(7);
});

it('formats Andorran addresses with the postcode left of the locality', function (): void {
    $formatted = app(AndorraAddressFormatter::class)->format(AddressData::from([
        'line1' => '12 AVINGUDA TORRENT PREGO',
        'line2' => 'BP 15',
        'city' => 'ANDORRA LA VELLA',
        'postcode' => 'AD501',
        'country_code' => 'AD',
    ]));

    expect($formatted)->toBe("12 AVINGUDA TORRENT PREGO\nBP 15\nAD501 ANDORRA LA VELLA\nAndorra");
});
it('formats Andorran street addresses with the parish postcode', function (): void {
    $formatted = app(AndorraAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Avinguda Meritxell 10',
        'city' => 'ANDORRA LA VELLA',
        'postcode' => 'AD500',
        'country_code' => 'AD',
    ]));

    expect($formatted)->toBe("Avinguda Meritxell 10\nAD500 ANDORRA LA VELLA\nAndorra");
});
