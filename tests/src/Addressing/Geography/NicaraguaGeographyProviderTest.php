<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Nicaragua\NicaraguaAddressFormatter;
use AIArmada\Addressing\Geography\Nicaragua\NicaraguaGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('formats Nicaraguan addresses with the postcode above the municipality', function (): void {
    $formatted = app(NicaraguaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Portón Cementerio General 1c Este, 1/2c Norte. Barrio Santa Ana Sur.',
        'city' => 'Managua',
        'state' => 'Managua',
        'postcode' => '12005',
        'country_code' => 'NI',
    ]));

    expect($formatted)->toBe("Portón Cementerio General 1c Este, 1/2c Norte. Barrio Santa Ana Sur.\n12005\nManagua\nNicaragua");
});
it('formats Nicaraguan Granada addresses with the town postcode', function (): void {
    $formatted = app(NicaraguaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Calle La Calzada 4',
        'city' => 'Granada',
        'postcode' => '43000',
        'country_code' => 'NI',
    ]));

    expect($formatted)->toBe("Calle La Calzada 4\n43000\nGranada\nNicaragua");
});

it('names the autonomous regions Costa Caribe with English aliases', function (): void {
    $areas = app(NicaraguaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('ni:autonomous_region:costa-caribe-norte')->name)->toBe('Costa Caribe Norte')
        ->and($areas->get('ni:autonomous_region:costa-caribe-norte')->code)->toBe('AN')
        ->and($areas->get('ni:autonomous_region:costa-caribe-sur')->name)->toBe('Costa Caribe Sur')
        ->and($areas->get('ni:autonomous_region:costa-caribe-sur')->code)->toBe('AS')
        ->and($areas->has('ni:autonomous_region:north-caribbean-coast'))->toBeFalse()
        ->and($areas->has('ni:autonomous_region:south-caribbean-coast'))->toBeFalse();

    $names = app(NicaraguaGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names['ni:autonomous_region:costa-caribe-norte'][0]['name'])->toBe('North Caribbean Coast')
        ->and($names['ni:autonomous_region:costa-caribe-sur'][0]['name'])->toBe('South Caribbean Coast');
});
