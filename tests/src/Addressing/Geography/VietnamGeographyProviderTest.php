<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Vietnam\VietnamAddressFormatter;
use AIArmada\Addressing\Geography\Vietnam\VietnamGeographyProvider;

it('formats Vietnamese addresses with the postcode right of the province', function (): void {
    $formatted = app(VietnamAddressFormatter::class)->format(AddressData::from([
        'line1' => 'No 5, Pham Hung Road',
        'city' => 'My Dinh 2 Ward',
        'state' => 'HANOI',
        'postcode' => '11517',
        'country_code' => 'VN',
    ]));

    expect($formatted)->toBe("No 5, Pham Hung Road\nMy Dinh 2 Ward\nHANOI 11517\nVietnam");
});

it('transliterates Đ to d in province slugs', function (): void {
    $areas = app(VietnamGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('vn:province:dak-lak')->name)->toBe('Đắk Lắk')
        ->and($areas->get('vn:province:dong-nai')->name)->toBe('Đồng Nai')
        ->and($areas->get('vn:province:dong-thap')->name)->toBe('Đồng Tháp')
        ->and($areas->get('vn:province:dien-bien')->name)->toBe('Điện Biên')
        ->and($areas->get('vn:municipality:da-nang')->name)->toBe('Đà Nẵng')
        ->and($areas->has('vn:province:ak-lak'))->toBeFalse();
});
