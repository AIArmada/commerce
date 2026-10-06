<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Mexico\MexicoGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('declares traditional abbreviations for all 32 states', function (): void {
    $names = app(MexicoGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names)->toHaveCount(32)
        ->and($names['mx:state:ciudad-de-mexico'][0])->toBe(['name' => 'CDMX', 'name_type' => 'abbreviation'])
        ->and($names['mx:state:estado-de-mexico'][0]['name'])->toBe('EDOMEX')
        ->and($names['mx:state:quintana-roo'][0]['name'])->toBe('Q. ROO');
});

it('pins the B21 tree pass: 10 municipio fixes, Las Casas held', function (): void {
    $areas = app(MexicoGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('mx:municipality:19009')->name)->toBe('Cadereyta Jiménez')
        ->and($areas->get('mx:municipality:20188')->name)->toBe('San Juan Colorado')
        ->and($areas->get('mx:municipality:11014')->name)->toBe('Dolores Hidalgo Cuna de la Independencia Nacional')
        ->and($areas->get('mx:municipality:14077')->name)->toBe('San Martín Hidalgo')
        ->and($areas->get('mx:municipality:15001')->name)->toBe('Acambay de Ruíz Castañeda')
        ->and($areas->get('mx:municipality:20124')->name)->toBe('San Blas Atempa')
        ->and($areas->get('mx:municipality:20334')->name)->toBe('Villa de Tututepec de Melchor Ocampo')
        ->and($areas->get('mx:municipality:29004')->name)->toBe('Atltzayanca')
        ->and($areas->get('mx:municipality:29037')->name)->toBe('Ziltlaltépec de Trinidad Sánchez Santos')
        ->and($areas->get('mx:municipality:30105')->name)->toBe('Medellín de Bravo')
        ->and($areas->get('mx:municipality:07078')->name)->toBe('San Cristóbal de Las Casas');
});

it('pins the B21 postal pass: PF1 Puerto Morelos pair, PF2 held out', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MX', $dir . '/mexico-postal-codes.csv', $dir . '/mexico-postal-code-areas.csv', 'aiarmada.addressing.mexico');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(32448)
        ->and($postcodes)->toHaveCount(32448)
        ->and($byCode->has('20388'))->toBeFalse();

    $primary = fn (string $code) => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    expect($primary('77580'))->toBe('mx:municipality:23011')
        ->and($primary('77586'))->toBe('mx:municipality:23011')
        ->and($primary('74801'))->toBe('mx:municipality:21157');
});
