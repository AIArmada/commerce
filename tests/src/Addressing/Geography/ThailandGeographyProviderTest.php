<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Thailand\ThailandAddressFormatter;
use AIArmada\Addressing\Geography\Thailand\ThailandGeographyProvider;

it('formats Thai addresses with district, province and postcode below', function (): void {
    $formatted = app(ThailandAddressFormatter::class)->format(AddressData::from([
        'line1' => '199/63 Moo 1, Tumbol Bangtalad',
        'city' => 'Amphoe Pak Kret',
        'state' => 'Nonthaburi',
        'postcode' => '11120',
        'country_code' => 'TH',
    ]));

    expect($formatted)->toBe("199/63 Moo 1, Tumbol Bangtalad\nAmphoe Pak Kret, Nonthaburi\n11120\nThailand");
});

it('ships 76 provinces plus Bangkok and Pattaya', function (): void {
    $areas = app(ThailandGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->where('level', 1))->toHaveCount(78)
        ->and($areas->get('th:metropolitan_administration:pattaya')->name)->toBe('Pattaya')
        ->and($areas->get('th:metropolitan_administration:pattaya')->code)->toBe('S')
        ->and($areas->get('th:metropolitan_administration:pattaya')->type)->toBe('metropolitan_administration');
});

it('ships 928 amphoe and khet under provinces with parent links', function (): void {
    $areas = app(ThailandGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(928)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($areas->where('type', 'khet'))->toHaveCount(50)
        ->and($byId->get('th:amphoe:mueang-chiang-mai')->code)->toBe('5001')
        ->and($byId->get('th:amphoe:mueang-bueng-kan')->code)->toBe('3801')
        ->and($byId->get('th:khet:bang-kapi')->code)->toBe('1006')
        ->and($byId->get('th:amphoe:galyani-vadhana')->code)->toBe('5026');
});
