<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\PuertoRico\PuertoRicoAddressFormatter;
use AIArmada\Addressing\Geography\PuertoRico\PuertoRicoGeographyProvider;

it('formats Puerto Rican addresses as locality PR ZIP', function (): void {
    $formatted = app(PuertoRicoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'URB LAS GLADIOLAS',
        'line2' => '150 CALLE A',
        'city' => 'SAN JUAN',
        'postcode' => '00926-0221',
        'country_code' => 'PR',
    ]));

    expect($formatted)->toBe("URB LAS GLADIOLAS\n150 CALLE A\nSAN JUAN PR 00926-0221\nPuerto Rico");
});
it('formats Puerto Rican addresses keeping the barrio above the ZIP line', function (): void {
    $formatted = app(PuertoRicoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Calle Luna 10',
        'city' => 'Santurce',
        'state' => 'San Juan',
        'postcode' => '00907',
        'country_code' => 'PR',
    ]));

    expect($formatted)->toBe("Calle Luna 10\nSanturce\nSan Juan PR 00907\nPuerto Rico");
});

it('types all 78 municipios as municipality with FIPS codes', function (): void {
    $areas = app(PuertoRicoGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas)->toHaveCount(78)
        ->and($areas->where('type', 'region'))->toBeEmpty()
        ->and($areas->get('pr:municipality:arecibo')->code)->toBe('013')
        ->and($areas->get('pr:municipality:bayamon')->name)->toBe('Bayamón')
        ->and($areas->get('pr:municipality:bayamon')->code)->toBe('021')
        ->and($areas->get('pr:municipality:caguas')->code)->toBe('025')
        ->and($areas->get('pr:municipality:carolina')->code)->toBe('031')
        ->and($areas->get('pr:municipality:guaynabo')->code)->toBe('061')
        ->and($areas->get('pr:municipality:mayaguez')->code)->toBe('097')
        ->and($areas->get('pr:municipality:ponce')->code)->toBe('113')
        ->and($areas->get('pr:municipality:san-juan')->code)->toBe('127')
        ->and($areas->get('pr:municipality:toa-baja')->code)->toBe('137')
        ->and($areas->get('pr:municipality:trujillo-alto')->code)->toBe('139')
        ->and($areas->has('pr:region:arecibo'))->toBeFalse();

    $mappings = app(PuertoRicoGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(78);

    foreach (['AR', 'BY', 'CG', 'CL', 'GN', 'MG', 'PO', 'SJ', 'TB', 'TA'] as $retiredCode) {
        expect($mappings)->not->toHaveKey($retiredCode);
    }
});
