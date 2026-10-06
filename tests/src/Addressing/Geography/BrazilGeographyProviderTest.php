<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Brazil\BrazilGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('declares UF abbreviations for all 27 states and the federal district', function (): void {
    $names = app(BrazilGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names)->toHaveCount(27)
        ->and($names['br:state:sao-paulo'][0])->toBe(['name' => 'SP', 'name_type' => 'abbreviation'])
        ->and($names['br:federal_district:distrito-federal'][0]['name'])->toBe('DF');
});

it('pins the B21 postal pass: RO renumber, Itapua dedup, Serra relink, 22 adds', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('BR', $dir . '/brazil-postal-codes.csv', $dir . '/brazil-postal-code-areas.csv', 'aiarmada.addressing.brazil');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(5547)
        ->and($postcodes)->toHaveCount(5547)
        ->and($byCode->has('78937-000'))->toBeFalse()
        ->and($byCode->has('78900-000'))->toBeTrue();

    $primary = fn (string $code) => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    expect($primary('76954-000'))->toBe('br:municipality:1100015')
        ->and($primary('76850-000'))->toBe('br:municipality:1100106')
        ->and($primary('76970-000'))->toBe('br:municipality:1100189')
        ->and($primary('76866-000'))->toBe('br:municipality:1101609')
        ->and($primary('76923-000'))->toBe('br:municipality:1101807')
        ->and($primary('76861-000'))->toBe('br:municipality:1101104')
        ->and($primary('68948-000'))->toBe('br:municipality:1600055')
        ->and($primary('68945-000'))->toBe('br:municipality:1600154')
        ->and($primary('68129-000'))->toBe('br:municipality:1504752')
        ->and($primary('95933-000'))->toBe('br:municipality:4304614')
        ->and($primary('75160-000'))->toBe('br:municipality:5204854')
        ->and($primary('76890-000'))->toBe('br:municipality:1100114')
        ->and($primary('69926-000'))->toBe('br:municipality:1200138')
        ->and($primary('58489-000'))->toBe('br:municipality:2501302');
});
