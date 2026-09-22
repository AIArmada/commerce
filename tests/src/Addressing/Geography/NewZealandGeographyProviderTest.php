<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\NewZealand\NewZealandAddressFormatter;
use AIArmada\Addressing\Geography\NewZealand\NewZealandGeographyProvider;

it('formats New Zealand addresses with the code left of the locality', function (): void {
    $formatted = app(NewZealandAddressFormatter::class)->format(AddressData::from([
        'line1' => '100 Queen Street',
        'city' => 'Auckland',
        'postcode' => '1010',
        'country_code' => 'NZ',
    ]));

    expect($formatted)->toBe("100 Queen Street\n1010 Auckland\nNew Zealand");
});

it('formats Wellington addresses with their own code', function (): void {
    $formatted = app(NewZealandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Wellington',
        'postcode' => '6011',
        'country_code' => 'NZ',
    ]));

    expect($formatted)->toBe("PO Box 1\n6011 Wellington\nNew Zealand");
});

it('ships 67 territorial authorities under regions with parent links', function (): void {
    $areas = app(NewZealandGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(67)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('type', 'district'))->toHaveCount(53)
        ->and($l2->where('type', 'city'))->toHaveCount(12)
        ->and($byId->get('nz:district:far-north')->parentSourceId)->toBe('nz:region:northland')
        ->and($byId->get('nz:district:waitaki')->parentSourceId)->toBe('nz:region:canterbury');
});
