<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Montserrat\MontserratAddressFormatter;
use AIArmada\Addressing\Geography\Montserrat\MontserratGeographyProvider;

it('formats Montserratian addresses with the postcode right of the locality', function (): void {
    $formatted = app(MontserratAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 140',
        'city' => 'Brades',
        'postcode' => 'MSR1110',
        'country_code' => 'MS',
    ]));

    expect($formatted)->toBe("PO Box 140\nBrades, MSR1110\nMontserrat");
});
it('formats Montserratian Olveston addresses with the district postcode', function (): void {
    $formatted = app(MontserratAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 12',
        'city' => 'Olveston',
        'postcode' => 'MSR1350',
        'country_code' => 'MS',
    ]));

    expect($formatted)->toBe("PO Box 12\nOlveston, MSR1350\nMontserrat");
});

it('ships the 4 parishes as terminal states', function (): void {
    $areas = app(MontserratGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(4)
        ->and($areas->pluck('parentSourceId')->filter()->isEmpty())->toBeTrue()
        ->and($byId->get('ms:parish:saint-patrick')->name)->toBe('Saint Patrick');
});
