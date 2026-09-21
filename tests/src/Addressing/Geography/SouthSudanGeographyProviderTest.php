<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SouthSudan\SouthSudanAddressFormatter;
use AIArmada\Addressing\Geography\SouthSudan\SouthSudanGeographyProvider;

it('formats South Sudanese addresses without a postcode system', function (): void {
    $formatted = app(SouthSudanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Plot 123, Hai Malakal',
        'city' => 'JUBA',
        'state' => 'Central Equatoria',
        'country_code' => 'SS',
    ]));

    expect($formatted)->toBe("Plot 123, Hai Malakal\nJUBA\nCentral Equatoria\nSouth Sudan");
});
it('prints any supplied South Sudanese code on its own line', function (): void {
    $formatted = app(SouthSudanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O Box 449',
        'city' => 'JUBA',
        'postcode' => '99999',
        'country_code' => 'SS',
    ]));

    expect($formatted)->toBe("P.O Box 449\nJUBA\n99999\nSouth Sudan");
});
it('exposes the corrected Jonglei state slug and name', function (): void {
    $areas = app(SouthSudanGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('ss:state:jonglei')->name)->toBe('Jonglei')
        ->and($areas->get('ss:state:jonglei')->code)->toBe('JG')
        ->and($areas->get('ss:state:jonglei')->type)->toBe('state')
        ->and($areas->has('ss:state:jonglei-state'))->toBeFalse();
});
