<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Monaco\MonacoAddressFormatter;
use AIArmada\Addressing\Geography\Monaco\MonacoGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(MonacoGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('quarter')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Monegasque tree with state links', function (): void {
    $this->seedCountry('MC');

    $result = app(SeedCountryGeographiesAction::class)->execute('MC');
    $country = AddressCountry::query()->where('iso2', 'MC')->firstOrFail();

    expect($result['seeded'])->toContain('MC')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(17)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(17)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'quarter')->count())->toBe(17)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(17);
});

it('formats Monegasque addresses with the postcode left of the locality', function (): void {
    $formatted = app(MonacoAddressFormatter::class)->format(AddressData::from([
        'line1' => '1 AVENUE DE L HERMITAGE',
        'city' => 'MONACO',
        'postcode' => '98000',
        'country_code' => 'MC',
    ]));

    expect($formatted)->toBe("1 AVENUE DE L HERMITAGE\n98000 MONACO\nMonaco");
});
it('formats Monegasque special delivery addresses with the office postcode', function (): void {
    $formatted = app(MonacoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 112',
        'city' => 'MONACO',
        'postcode' => '98001',
        'country_code' => 'MC',
    ]));

    expect($formatted)->toBe("BP 112\n98001 MONACO\nMonaco");
});
