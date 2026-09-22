<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Lesotho\LesothoAddressFormatter;
use AIArmada\Addressing\Geography\Lesotho\LesothoGeographyProvider;

it('formats Basotho addresses with the postcode right of the locality', function (): void {
    $formatted = app(LesothoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 500',
        'city' => 'MASERU',
        'postcode' => '100',
        'country_code' => 'LS',
    ]));

    expect($formatted)->toBe("P.O. Box 500\nMASERU 100\nLesotho");
});
it('prints matching Basotho city and district once when the postcode is missing', function (): void {
    $formatted = app(LesothoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 500',
        'city' => 'Maseru',
        'state' => 'Maseru',
        'country_code' => 'LS',
    ]));

    expect($formatted)->toBe("P.O. Box 500\nMaseru\nLesotho");
});

it('ships 80 constituencys under districts with parent links', function (): void {
    $areas = app(LesothoGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'constituency');

    expect($l2)->toHaveCount(80)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('ls:constituency:taung')->name)->toBe('Taung')
        ->and($byId->get('ls:constituency:mechachane')->name)->toBe('Mechachane')
        ->and($byId->get('ls:constituency:qacha-s-nek')->name)->toBe("Qacha's Nek");
});
