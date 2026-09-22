<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Benin\BeninAddressFormatter;
use AIArmada\Addressing\Geography\Benin\BeninGeographyProvider;

it('formats Beninese addresses without a postcode system', function (): void {
    $formatted = app(BeninAddressFormatter::class)->format(AddressData::from([
        'line1' => '10 BP 648',
        'city' => 'COTONOU',
        'country_code' => 'BJ',
    ]));

    expect($formatted)->toBe("10 BP 648\nCOTONOU\nBenin");
});
it('prints any supplied Beninese code on its own line', function (): void {
    $formatted = app(BeninAddressFormatter::class)->format(AddressData::from([
        'line1' => '10 BP 648',
        'city' => 'COTONOU',
        'postcode' => '99999',
        'country_code' => 'BJ',
    ]));

    expect($formatted)->toBe("10 BP 648\nCOTONOU\n99999\nBenin");
});

it('ships 77 communes under departments with parent links', function (): void {
    $areas = app(BeninGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'commune');

    expect($l2)->toHaveCount(77)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('bj:commune:kandi')->name)->toBe('Kandi')
        ->and($byId->get('bj:commune:natitingou')->name)->toBe('Natitingou')
        ->and($byId->get('bj:commune:ouidah')->name)->toBe('Ouidah');
});
