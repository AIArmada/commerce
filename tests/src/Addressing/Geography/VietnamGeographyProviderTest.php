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
        ->and($areas->get('vn:municipality:dong-nai')->name)->toBe('Đồng Nai')
        ->and($areas->get('vn:province:dong-thap')->name)->toBe('Đồng Tháp')
        ->and($areas->get('vn:province:dien-bien')->name)->toBe('Điện Biên')
        ->and($areas->get('vn:municipality:da-nang')->name)->toBe('Đà Nẵng')
        ->and($areas->has('vn:province:ak-lak'))->toBeFalse();
});

it('types the 2026 municipalities Quảng Ninh, Bắc Ninh and Đồng Nai', function (): void {
    $areas = app(VietnamGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('vn:municipality:quang-ninh')->type)->toBe('municipality')
        ->and($areas->get('vn:municipality:quang-ninh')->code)->toBe('13')
        ->and($areas->get('vn:municipality:bac-ninh')->type)->toBe('municipality')
        ->and($areas->get('vn:municipality:bac-ninh')->code)->toBe('56')
        ->and($areas->get('vn:municipality:dong-nai')->type)->toBe('municipality')
        ->and($areas->get('vn:municipality:dong-nai')->code)->toBe('39')
        ->and($areas->has('vn:province:quang-ninh'))->toBeFalse()
        ->and($areas->has('vn:province:bac-ninh'))->toBeFalse()
        ->and($areas->has('vn:province:dong-nai'))->toBeFalse();
});

it('ships 3321 communes, wards and special zones under provinces', function (): void {
    $areas = app(VietnamGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(3321)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('type', 'commune'))->toHaveCount(2599)
        ->and($l2->where('type', 'ward'))->toHaveCount(709)
        ->and($l2->where('type', 'special_zone'))->toHaveCount(13)
        ->and($byId->get('vn:ward:ba-dinh')->parentSourceId)->toBe('vn:municipality:ha-noi')
        ->and($byId->get('vn:special_zone:hoang-sa')->parentSourceId)->toBe('vn:municipality:da-nang');
});

it('labels tiers Tỉnh, Thành phố, Xã, Phường and Đặc khu', function (): void {
    $provider = app(VietnamGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['province' => 'Tỉnh', 'municipality' => 'Thành phố', 'commune' => 'Xã', 'ward' => 'Phường', 'special_zone' => 'Đặc khu'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
