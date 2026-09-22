<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Lebanon\LebanonAddressFormatter;
use AIArmada\Addressing\Geography\Lebanon\LebanonGeographyProvider;

it('formats Lebanese addresses with the postcode right of the locality', function (): void {
    $formatted = app(LebanonAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Building Al Amal, 2nd floor',
        'line2' => 'Australia Street',
        'city' => 'Raoucheh',
        'state' => 'Beirut',
        'postcode' => '1107 2080',
        'country_code' => 'LB',
    ]));

    expect($formatted)->toBe("Building Al Amal, 2nd floor\nAustralia Street\nRaoucheh 1107 2080\nBeirut\nLebanon");
});
it('formats Lebanese addresses without a postcode when missing', function (): void {
    $formatted = app(LebanonAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Building Al Amal, 2nd floor',
        'city' => 'Raoucheh',
        'state' => 'Beirut',
        'country_code' => 'LB',
    ]));

    expect($formatted)->toBe("Building Al Amal, 2nd floor\nRaoucheh\nBeirut\nLebanon");
});

it('ships 25 cazas under governorates with parent links', function (): void {
    $areas = app(LebanonGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'caza');

    expect($l2)->toHaveCount(25)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('lb:caza:akkar')->name)->toBe('Akkar')
        ->and($byId->get('lb:caza:byblos')->name)->toBe('Byblos')
        ->and($byId->get('lb:caza:tripoli')->name)->toBe('Tripoli');
});
