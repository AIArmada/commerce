<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Kosovo\KosovoAddressFormatter;
use AIArmada\Addressing\Geography\Kosovo\KosovoGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(KosovoGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Kosovar tree with state links', function (): void {
    $this->seedCountry('XK');

    $result = app(SeedCountryGeographiesAction::class)->execute('XK');
    $country = AddressCountry::query()->where('iso2', 'XK')->firstOrFail();

    expect($result['seeded'])->toContain('XK')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(7)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(7);
});

it('formats Kosovar addresses with the postcode left of the locality', function (): void {
    $formatted = app(KosovoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rruga Lidhja e Prizrenit 10',
        'city' => 'Pristina',
        'postcode' => '10000',
        'country_code' => 'XK',
    ]));

    expect($formatted)->toBe("Rruga Lidhja e Prizrenit 10\n10000 Pristina\nKosovo");
});
it('prints matching Kosovar city and district once', function (): void {
    $formatted = app(KosovoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rruga Adem Jashari 1',
        'city' => 'Prizren',
        'state' => 'Prizren',
        'postcode' => '20000',
        'country_code' => 'XK',
    ]));

    expect($formatted)->toBe("Rruga Adem Jashari 1\n20000 Prizren\nKosovo");
});
