<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Mayotte\MayotteAddressFormatter;
use AIArmada\Addressing\Geography\Mayotte\MayotteGeographyProvider;

it('formats Mahoran addresses with the code left of the locality', function (): void {
    $formatted = app(MayotteAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de la Mairie',
        'city' => 'MAMOUDZOU',
        'postcode' => '97600',
        'country_code' => 'YT',
    ]));

    expect($formatted)->toBe("Rue de la Mairie\n97600 MAMOUDZOU\nMayotte");
});

it('formats Chirongui addresses with their own code', function (): void {
    $formatted = app(MayotteAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 21',
        'city' => 'CHIRONGUI',
        'postcode' => '97620',
        'country_code' => 'YT',
    ]));

    expect($formatted)->toBe("BP 21\n97620 CHIRONGUI\nMayotte");
});

it('ships the 17 communes as terminal states', function (): void {
    $areas = app(MayotteGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(17)
        ->and($areas->pluck('parentSourceId')->filter()->isEmpty())->toBeTrue()
        ->and($byId->get('yt:commune:acoua')->name)->toBe('Acoua');
});
