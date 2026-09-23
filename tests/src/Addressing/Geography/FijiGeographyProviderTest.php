<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Fiji\FijiAddressFormatter;
use AIArmada\Addressing\Geography\Fiji\FijiGeographyProvider;

it('formats Fijian addresses without a postcode system', function (): void {
    $formatted = app(FijiAddressFormatter::class)->format(AddressData::from([
        'line1' => '14 VIRIA STREET',
        'line2' => 'VATUWAQA',
        'city' => 'SUVA',
        'country_code' => 'FJ',
    ]));

    expect($formatted)->toBe("14 VIRIA STREET\nVATUWAQA\nSUVA\nFiji Islands");
});
it('prints any supplied Fijian code on its own line', function (): void {
    $formatted = app(FijiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 123',
        'city' => 'SUVA',
        'postcode' => '9999',
        'country_code' => 'FJ',
    ]));

    expect($formatted)->toBe("PO Box 123\nSUVA\n9999\nFiji Islands");
});

it('ships 14 provinces under divisions with parent links', function (): void {
    $areas = app(FijiGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'province');

    expect($l2)->toHaveCount(14)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('fj:province:ba')->name)->toBe('Ba')
        ->and($byId->get('fj:province:cakaudrove')->parentSourceId)->toBe('fj:division:northern');
});
