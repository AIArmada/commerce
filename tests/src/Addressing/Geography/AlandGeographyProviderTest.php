<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Aland\AlandAddressFormatter;
use AIArmada\Addressing\Geography\Aland\AlandGeographyProvider;

it('formats Alander addresses with the prefixed postcode left of the town', function (): void {
    $formatted = app(AlandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Stadshusparken',
        'city' => 'MARIEHAMN',
        'postcode' => 'AX-22100',
        'country_code' => 'AX',
    ]));

    expect($formatted)->toBe("Stadshusparken\nAX-22100 MARIEHAMN\nAland Islands");
});
it('formats Alander domestic addresses with a bare postcode', function (): void {
    $formatted = app(AlandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Stadshusparken',
        'city' => 'MARIEHAMN',
        'postcode' => '22100',
        'country_code' => 'AX',
    ]));

    expect($formatted)->toBe("Stadshusparken\n22100 MARIEHAMN\nAland Islands");
});

it('labels municipalities Kommun', function (): void {
    $provider = app(AlandGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['municipality' => 'Kommun'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
