<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Iceland\IcelandAddressFormatter;
use AIArmada\Addressing\Geography\Iceland\IcelandGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(IcelandGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Icelandic tree with state links', function (): void {
    $this->seedCountry('IS');

    $result = app(SeedCountryGeographiesAction::class)->execute('IS');
    $country = AddressCountry::query()->where('iso2', 'IS')->firstOrFail();

    expect($result['seeded'])->toContain('IS')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(72)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(72)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(8)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(64)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(72);
});

it('formats Icelandic addresses with the postcode left of the locality', function (): void {
    $formatted = app(IcelandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Tryggvagötu 5',
        'city' => 'HAFNARFIRÐI',
        'postcode' => '220',
        'country_code' => 'IS',
    ]));

    expect($formatted)->toBe("Tryggvagötu 5\n220 HAFNARFIRÐI\nIceland");
});
it('formats Icelandic capital addresses with the town postcode', function (): void {
    $formatted = app(IcelandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Ingólfsstræti 3',
        'city' => 'REYKJAVÍK',
        'postcode' => '121',
        'country_code' => 'IS',
    ]));

    expect($formatted)->toBe("Ingólfsstræti 3\n121 REYKJAVÍK\nIceland");
});
