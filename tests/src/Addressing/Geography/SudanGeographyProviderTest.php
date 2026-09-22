<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Sudan\SudanAddressFormatter;
use AIArmada\Addressing\Geography\Sudan\SudanGeographyProvider;

it('formats Sudanese addresses with the postcode above the locality', function (): void {
    $formatted = app(SudanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'B.P. 211',
        'city' => 'KHARTOUM',
        'postcode' => '11111',
        'country_code' => 'SD',
    ]));

    expect($formatted)->toBe("B.P. 211\n11111\nKHARTOUM\nSudan");
});

it('ships 188 districts under states with parent links', function (): void {
    $areas = app(SudanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($l2)->toHaveCount(188)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('sd:district:al-kamlin')->name)->toBe('Al Kamlin')
        ->and($byId->get('sd:district:abyei')->name)->toBe('Abyei')
        ->and($byId->get('sd:district:north-kordofan:ar-rahad')->name)->toBe('Ar Rahad');
});
