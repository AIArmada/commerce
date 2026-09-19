<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Reunion\ReunionAddressFormatter;
use AIArmada\Addressing\Geography\Reunion\ReunionGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(ReunionGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Réunionese tree with state links', function (): void {
    $this->seedCountry('RE');

    $result = app(SeedCountryGeographiesAction::class)->execute('RE');
    $country = AddressCountry::query()->where('iso2', 'RE')->firstOrFail();

    expect($result['seeded'])->toContain('RE')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(4)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(4)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(4)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(4);
});

it('formats Reunionese addresses with the code left of the locality', function (): void {
    $formatted = app(ReunionAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de Paris',
        'city' => 'SAINT-DENIS',
        'postcode' => '97400',
        'country_code' => 'RE',
    ]));

    expect($formatted)->toBe("Rue de Paris\n97400 SAINT-DENIS\nReunion");
});

it('formats Saint-Pierre addresses with their own code', function (): void {
    $formatted = app(ReunionAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 300',
        'city' => 'SAINT-PIERRE',
        'postcode' => '97410',
        'country_code' => 'RE',
    ]));

    expect($formatted)->toBe("BP 300\n97410 SAINT-PIERRE\nReunion");
});
