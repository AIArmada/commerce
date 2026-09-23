<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Kosovo\KosovoAddressFormatter;
use AIArmada\Addressing\Geography\Kosovo\KosovoGeographyProvider;

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
it('exposes the corrected Gjakova district slug and name', function (): void {
    $areas = app(KosovoGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('xk:district:gjakova')->name)->toBe('Gjakova')
        ->and($areas->get('xk:district:gjakova')->code)->toBe('XDG')
        ->and($areas->get('xk:district:gjakova')->type)->toBe('district')
        ->and($areas->has('xk:district:gjakove'))->toBeFalse();
});

it('ships 38 municipalities under districts with parent links', function (): void {
    $areas = app(KosovoGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'municipality');

    expect($l2)->toHaveCount(38)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('xk:municipality:pristina')->name)->toBe('Pristina')
        ->and($byId->get('xk:municipality:prizren')->name)->toBe('Prizren')
        ->and($byId->get('xk:municipality:mitrovica')->name)->toBe('Mitrovica');
});

it('labels tiers Rajoni and Komuna', function (): void {
    $provider = app(KosovoGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['district' => 'Rajoni', 'municipality' => 'Komuna'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
