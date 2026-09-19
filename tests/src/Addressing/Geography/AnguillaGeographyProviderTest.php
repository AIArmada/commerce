<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Anguilla\AnguillaAddressFormatter;
use AIArmada\Addressing\Geography\Anguilla\AnguillaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(AnguillaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Anguillan tree with state links', function (): void {
    $this->seedCountry('AI');

    $result = app(SeedCountryGeographiesAction::class)->execute('AI');
    $country = AddressCountry::query()->where('iso2', 'AI')->firstOrFail();

    expect($result['seeded'])->toContain('AI')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(14)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(14);
});

it('formats Anguillan addresses with the single code below the locality', function (): void {
    $formatted = app(AnguillaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 60',
        'city' => 'The Valley',
        'postcode' => 'AI-2640',
        'country_code' => 'AI',
    ]));

    expect($formatted)->toBe("P.O. Box 60\nThe Valley\nAI-2640\nAnguilla");
});
it('formats Anguillan addresses without a postcode when missing', function (): void {
    $formatted = app(AnguillaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 60',
        'city' => 'The Valley',
        'country_code' => 'AI',
    ]));

    expect($formatted)->toBe("P.O. Box 60\nThe Valley\nAnguilla");
});
