<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Tunisia\TunisiaAddressFormatter;
use AIArmada\Addressing\Geography\Tunisia\TunisiaGeographyProvider;

it('formats Tunisian addresses with the postcode left of the locality', function (): void {
    $formatted = app(TunisiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '15 AVENUE BOURGUIBA',
        'city' => 'BOU SALEM',
        'postcode' => '8170',
        'country_code' => 'TN',
    ]));

    expect($formatted)->toBe("15 AVENUE BOURGUIBA\n8170 BOU SALEM\nTunisia");
});
it('prints matching Tunisian city and governorate once', function (): void {
    $formatted = app(TunisiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '15 AVENUE BOURGUIBA',
        'city' => 'TUNIS',
        'state' => 'Tunis',
        'postcode' => '1002',
        'country_code' => 'TN',
    ]));

    expect($formatted)->toBe("15 AVENUE BOURGUIBA\n1002 TUNIS\nTunisia");
});

it('ships 279 delegations under governorates with parent links', function (): void {
    $areas = app(TunisiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'delegation');

    expect($l2)->toHaveCount(279)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('tn:delegation:ariana-ville')->name)->toBe('Ariana Ville')
        ->and($byId->get('tn:delegation:beja-nord')->name)->toBe('Béja Nord')
        ->and($byId->get('tn:delegation:hammamet')->name)->toBe('Hammamet');
});
