<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Tonga\TongaAddressFormatter;
use AIArmada\Addressing\Geography\Tonga\TongaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(TongaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('division')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Tongan tree with state links', function (): void {
    $this->seedCountry('TO');

    $result = app(SeedCountryGeographiesAction::class)->execute('TO');
    $country = AddressCountry::query()->where('iso2', 'TO')->firstOrFail();

    expect($result['seeded'])->toContain('TO')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'division')->count())->toBe(5)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(5);
});

it('formats Tongan addresses without a postcode system', function (): void {
    $formatted = app(TongaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Nuku’alofa',
        'country_code' => 'TO',
    ]));

    expect($formatted)->toBe("PO Box 1\nNuku’alofa\nTonga");
});

it('prints any supplied Tongan code on its own line', function (): void {
    $formatted = app(TongaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Nuku’alofa',
        'postcode' => '99999',
        'country_code' => 'TO',
    ]));

    expect($formatted)->toBe("PO Box 1\nNuku’alofa\n99999\nTonga");
});
