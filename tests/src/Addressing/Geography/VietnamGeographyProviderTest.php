<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Vietnam\VietnamGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

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

it('pins the B21 verify-only pass: MOST-exact set, H1/H2/H3 holds', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('VN', $dir . '/vietnam-postal-codes.csv', $dir . '/vietnam-postal-code-areas.csv', 'aiarmada.addressing.vietnam');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(3320)
        ->and($postcodes)->toHaveCount(3320)
        ->and($byCode->has('05127'))->toBeFalse();

    $primary = fn (string $code) => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    expect($primary('15221'))->toBe('vn:commune:tam-duong-bac')
        ->and($primary('26932'))->toBe('vn:ward:bac-ninh:hiep-hoa');

    $areas = app(VietnamGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    // H1 2026 conversions stay wards; H2 Nghi Duong present but unlegged;
    // H3 keeps bundle oà; K8 lam-ong slug kept as internal key.
    expect($byId->get('vn:ward:bo-ha')->type)->toBe('ward')
        ->and($byId->get('vn:ward:kep')->type)->toBe('ward')
        ->and($byId->get('vn:ward:bac-ninh:hiep-hoa')->name)->toBe('Hiệp Hoà')
        ->and($byId->has('vn:commune:nghi-duong'))->toBeTrue()
        ->and($byId->get('vn:province:lam-ong')->name)->toBe('Lâm Đồng')
        ->and($byId->get('vn:municipality:hue')->code)->toBe('26');
});
