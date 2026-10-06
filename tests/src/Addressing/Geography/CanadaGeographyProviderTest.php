<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Canada\CanadaGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('declares postal abbreviations for all 13 provinces and territories', function (): void {
    $names = app(CanadaGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names)->toHaveCount(13)
        ->and($names['ca:province:ontario'][0])->toBe(['name' => 'ON', 'name_type' => 'abbreviation'])
        ->and($names['ca:province:quebec'][0]['name'])->toBe('QC')
        ->and($names['ca:province:quebec'][1])->toBe(['name' => 'Québec', 'name_type' => 'alternative'])
        ->and($names['ca:territory:nunavut'][0]['name'])->toBe('NU');
});

it('pins the B21 pass: Sambaa K’e repair, G0B/H4Z drops, 14 FSA adds', function (): void {
    $areas = app(CanadaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(5041)
        ->and($byId->get('ca:municipality:6104006')->name)->toBe('Sambaa K’e');

    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('CA', $dir . '/canada-postal-codes.csv', $dir . '/canada-postal-code-areas.csv', 'aiarmada.addressing.canada');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(1663)
        ->and($postcodes)->toHaveCount(1673)
        ->and($byCode->has('G0B'))->toBeFalse()
        ->and($byCode->has('H4Z'))->toBeFalse();

    $primary = fn (string $code) => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    expect($primary('J5N'))->toBe('ca:municipality:2473035')
        ->and($primary('L3L'))->toBe('ca:municipality:3519028')
        ->and($primary('R5J'))->toBe('ca:municipality:4602069')
        ->and($primary('R5N'))->toBe('ca:municipality:4612047')
        ->and($primary('S7A'))->toBe('ca:municipality:4715019')
        ->and($primary('T6Y'))->toBe('ca:municipality:4811061')
        ->and($byCode->get('V7Z'))->toHaveCount(2)
        ->and($byCode->get('S7B'))->toHaveCount(1);
});
