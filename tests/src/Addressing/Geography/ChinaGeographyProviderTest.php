<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\China\ChinaAddressFormatter;
use AIArmada\Addressing\Geography\China\ChinaGeographyProvider;

it('formats Chinese addresses with the postcode left of the province', function (): void {
    $formatted = app(ChinaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'No.1 Jianguomenwai Avenue',
        'state' => 'BEIJING',
        'postcode' => '100004',
        'country_code' => 'CN',
    ]));

    expect($formatted)->toBe("No.1 Jianguomenwai Avenue\n100004 BEIJING\nChina");
});
it('formats Chinese addresses with the sub-province above the postcode province line', function (): void {
    $formatted = app(ChinaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'No.12 Zhichun Road',
        'city' => 'Haidian District',
        'state' => 'BEIJING',
        'postcode' => '100191',
        'country_code' => 'CN',
    ]));

    expect($formatted)->toBe("No.12 Zhichun Road\nHaidian District\n100191 BEIJING\nChina");
});

it('ships 33 province-level divisions without Taiwan', function (): void {
    $areas = app(ChinaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $l1 = $areas->where('level', 1);

    expect($l1)->toHaveCount(33)
        ->and($l1->where('type', 'province'))->toHaveCount(22)
        ->and($l1->where('type', 'autonomous_region'))->toHaveCount(5)
        ->and($l1->where('type', 'municipality'))->toHaveCount(4)
        ->and($l1->where('type', 'special_administrative_region'))->toHaveCount(2)
        ->and($l1->pluck('name')->contains('Taiwan'))->toBeFalse();
});

it('ships 333 prefecture-level divisions under provinces with parent links', function (): void {
    $areas = app(ChinaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(333)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($areas->where('type', 'prefecture_city'))->toHaveCount(293)
        ->and($areas->where('type', 'autonomous_prefecture'))->toHaveCount(30)
        ->and($areas->where('type', 'prefecture'))->toHaveCount(7)
        ->and($areas->where('type', 'league'))->toHaveCount(3)
        ->and($byId->get('cn:prefecture_city:suzhou-anhui')->name)->toBe('Suzhou, Anhui')
        ->and($byId->get('cn:prefecture_city:suzhou-jiangsu')->parentSourceId)->toBe('cn:province:jiangsu')
        ->and($byId->get('cn:autonomous_prefecture:yanbian')->parentSourceId)->toBe('cn:province:jilin')
        ->and($byId->get('cn:prefecture:ngari')->parentSourceId)->toBe('cn:autonomous_region:tibet')
        ->and($byId->get('cn:league:alxa')->parentSourceId)->toBe('cn:autonomous_region:inner-mongolia');
});

it('exposes the prefecture level in the address hierarchy', function (): void {
    $levels = collect(app(ChinaGeographyProvider::class)->addressHierarchies()[0]->levels)->keyBy->key;

    expect($levels->has('prefecture'))->toBeTrue()
        ->and($levels->get('prefecture')->parentKey)->toBe('province');
});
