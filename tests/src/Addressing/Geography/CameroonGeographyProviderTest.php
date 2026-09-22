<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Cameroon\CameroonAddressFormatter;
use AIArmada\Addressing\Geography\Cameroon\CameroonGeographyProvider;

it('formats Cameroonian addresses without a postcode line', function (): void {
    $formatted = app(CameroonAddressFormatter::class)->format(AddressData::from([
        'line1' => 'B.P. 8035',
        'city' => 'YAOUNDE',
        'country_code' => 'CM',
    ]));

    expect($formatted)->toBe("B.P. 8035\nYAOUNDE\nCameroon");
});

it('ships 58 departments under regions with parent links', function (): void {
    $areas = app(CameroonGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'department');

    expect($l2)->toHaveCount(58)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('cm:department:djerem')->name)->toBe('Djérem')
        ->and($byId->get('cm:department:mfoundi')->name)->toBe('Mfoundi')
        ->and($byId->get('cm:department:fako')->name)->toBe('Fako');
});
