<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Mauritius\MauritiusAddressFormatter;
use AIArmada\Addressing\Geography\Mauritius\MauritiusGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(MauritiusGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Mauritian tree with state links', function (): void {
    $this->seedCountry('MU');

    $result = app(SeedCountryGeographiesAction::class)->execute('MU');
    $country = AddressCountry::query()->where('iso2', 'MU')->firstOrFail();

    expect($result['seeded'])->toContain('MU')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(12)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(12)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'dependency')->count())->toBe(3)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(12);
});

it('formats Mauritian addresses with the postcode right of the locality', function (): void {
    $formatted = app(MauritiusAddressFormatter::class)->format(AddressData::from([
        'line1' => '10, rue Claude Delaître',
        'line2' => 'Les Guibies',
        'city' => 'PORT LOUIS',
        'postcode' => '11213',
        'country_code' => 'MU',
    ]));

    expect($formatted)->toBe("10, rue Claude Delaître\nLes Guibies\nPORT LOUIS 11213\nMauritius");
});
it('formats Rodriguan addresses with the R postcode right of the locality', function (): void {
    $formatted = app(MauritiusAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de la Solidarité',
        'city' => 'Port Mathurin',
        'state' => 'Rodrigues Island',
        'postcode' => 'R5135',
        'country_code' => 'MU',
    ]));

    expect($formatted)->toBe("Rue de la Solidarité\nPort Mathurin R5135\nRodrigues Island\nMauritius");
});
