<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Thailand\ThailandGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

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

it('pins the B20 postal fixes: remaps, Surin cluster, Bangkok legs, wrong-code moves', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('TH', $dir . '/thailand-postal-codes.csv', $dir . '/thailand-postal-code-areas.csv', 'aiarmada.addressing.thailand');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(789)
        ->and($postcodes)->toHaveCount(930);

    $primary = fn (string $code) => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    // Misassigned-leg remaps.
    expect($primary('67000'))->toBe('th:amphoe:mueang-phetchabun')
        ->and($primary('43170'))->toBe('th:amphoe:so-phisai')
        // Surin cluster + Bangkok fills.
        ->and($primary('32000'))->toBe('th:amphoe:mueang-surin')
        ->and($byCode->get('32000')->pluck('areaSourceId')->contains('th:amphoe:khwao-sinarin'))->toBeTrue()
        ->and($primary('32230'))->toBe('th:amphoe:buachet')
        ->and($primary('10900'))->toBe('th:khet:chatuchak')
        ->and($byCode->get('10110')->pluck('areaSourceId')->contains('th:khet:khlong-toei'))->toBeTrue()
        ->and($byCode->get('50270')->pluck('areaSourceId')->contains('th:amphoe:galyani-vadhana'))->toBeTrue()
        ->and($primary('42190'))->toBe('th:amphoe:nong-hin')
        // Wrong-code moves with singles left behind.
        ->and($primary('23170'))->toBe('th:amphoe:ko-chang')
        ->and($byCode->get('23120'))->toHaveCount(1)
        ->and($primary('42220'))->toBe('th:amphoe:erawan')
        ->and($primary('41280'))->toBe('th:amphoe:wang-sam-mo')
        ->and($primary('41380'))->toBe('th:amphoe:na-yung');
});
