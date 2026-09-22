<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Gabon\GabonAddressFormatter;
use AIArmada\Addressing\Geography\Gabon\GabonGeographyProvider;

it('formats Gabonese addresses with the zone left of the locality', function (): void {
    $formatted = app(GabonAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 13210',
        'city' => 'LIBREVILLE',
        'postcode' => '01',
        'country_code' => 'GA',
    ]));

    expect($formatted)->toBe("BP 13210\n01 LIBREVILLE\nGabon");
});
it('formats Gabonese addresses with the province below the postcode line', function (): void {
    $formatted = app(GabonAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 45',
        'city' => 'TCHIBANGA',
        'state' => 'Nyanga',
        'postcode' => '05',
        'country_code' => 'GA',
    ]));

    expect($formatted)->toBe("BP 45\n05 TCHIBANGA\nNyanga\nGabon");
});

it('ships 49 departments under provinces with parent links', function (): void {
    $areas = app(GabonGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'department');

    expect($l2)->toHaveCount(49)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('ga:department:komo')->name)->toBe('Komo')
        ->and($byId->get('ga:department:noya')->name)->toBe('Noya')
        ->and($byId->get('ga:department:libreville')->name)->toBe('Libreville');
});
