<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SierraLeone\SierraLeoneAddressFormatter;
use AIArmada\Addressing\Geography\SierraLeone\SierraLeoneGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SierraLeoneGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Sierra Leonean tree with state links', function (): void {
    $this->seedCountry('SL');

    $result = app(SeedCountryGeographiesAction::class)->execute('SL');
    $country = AddressCountry::query()->where('iso2', 'SL')->firstOrFail();

    expect($result['seeded'])->toContain('SL')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(4)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'area')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(5);
});

it('formats Sierra Leonean addresses without a postcode system', function (): void {
    $formatted = app(SierraLeoneAddressFormatter::class)->format(AddressData::from([
        'line1' => '7A Ross Road Cline',
        'city' => 'FREETOWN',
        'country_code' => 'SL',
    ]));

    expect($formatted)->toBe("7A Ross Road Cline\nFREETOWN\nSierra Leone");
});
it('formats Sierra Leonean addresses with the province below the locality', function (): void {
    $formatted = app(SierraLeoneAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Bojon Street',
        'city' => 'Bo',
        'state' => 'Southern',
        'country_code' => 'SL',
    ]));

    expect($formatted)->toBe("Bojon Street\nBo\nSouthern\nSierra Leone");
});
