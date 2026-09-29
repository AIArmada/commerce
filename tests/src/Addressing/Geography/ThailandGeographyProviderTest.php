<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Thailand\ThailandGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

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

it('declares Bangkok official Thai name and Pattaya ISO spelling', function (): void {
    $names = app(ThailandGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names['th:metropolitan_administration:bangkok'][0])->toBe(['name' => 'Krung Thep Maha Nakhon', 'name_type' => 'official'])
        ->and($names['th:metropolitan_administration:pattaya'][0]['name'])->toBe('Phatthaya');
});
