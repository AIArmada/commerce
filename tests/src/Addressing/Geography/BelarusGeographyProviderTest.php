<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Belarus\BelarusAddressFormatter;
use AIArmada\Addressing\Geography\Belarus\BelarusGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(BelarusGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('oblast')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Belarusian tree with state links', function (): void {
    $this->seedCountry('BY');

    $result = app(SeedCountryGeographiesAction::class)->execute('BY');
    $country = AddressCountry::query()->where('iso2', 'BY')->firstOrFail();

    expect($result['seeded'])->toContain('BY')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'oblast')->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(7);
});

it('formats Belarusian addresses with the postcode and comma left of the locality', function (): void {
    $formatted = app(BelarusAddressFormatter::class)->format(AddressData::from([
        'line1' => 'pr-t Masherova, d.1, kv.12',
        'city' => 'Minsk',
        'postcode' => '220005',
        'country_code' => 'BY',
    ]));

    expect($formatted)->toBe("pr-t Masherova, d.1, kv.12\n220005, Minsk\nBelarus");
});
it('formats Belarusian rural addresses with the oblast below the postcode line', function (): void {
    $formatted = app(BelarusAddressFormatter::class)->format(AddressData::from([
        'line1' => 'ul. Lenina, d.3',
        'city' => 'Brest',
        'state' => 'Brest',
        'postcode' => '224000',
        'country_code' => 'BY',
    ]));

    expect($formatted)->toBe("ul. Lenina, d.3\n224000, Brest\nBelarus");
});
