<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Venezuela\VenezuelaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 25 L1 areas with 335 municipalities under L1 parents', function (): void {
    $areas = app(VenezuelaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);
    $l2 = $areas->where('level', 2);

    expect($areas)->toHaveCount(360)
        ->and($l1)->toHaveCount(25)
        ->and($l2)->toHaveCount(335)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue();

    expect($byId->get('ve:state:bolivar')->code)->toBe('F')
        ->and($byId->get('ve:state:zulia')->code)->toBe('V')
        ->and($byId->get('ve:capital_district:distrito-capital')->code)->toBe('A')
        ->and($byId->get('ve:federal_dependency:dependencias-federales')->code)->toBe('W')
        ->and($byId->has('ve:municipality:heres'))->toBeFalse()
        ->and($byId->has('ve:municipality:raul-leoni'))->toBeFalse();
});

it('pins the B18 Bolívar renames', function (): void {
    $areas = app(VenezuelaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($byId->get('ve:municipality:angostura-del-orinoco')->name)->toBe('Angostura del Orinoco')
        ->and($byId->get('ve:municipality:angostura-del-orinoco')->parentSourceId)->toBe('ve:state:bolivar')
        ->and($byId->get('ve:municipality:angostura')->name)->toBe('Angostura')
        ->and($byId->get('ve:municipality:angostura')->parentSourceId)->toBe('ve:state:bolivar');
});

it('pins 452 codes with the 5 reconfirmed shared codes plus 8 r2 adds', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('VE', $dir . '/venezuela-postal-codes.csv', $dir . '/venezuela-postal-code-areas.csv', 'aiarmada.addressing.venezuela');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(452)
        ->and($postcodes)->toHaveCount(458);

    $primary = fn (string $code) => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    expect($primary('2301'))->toBe('ve:state:guarico')
        ->and($primary('2334'))->toBe('ve:state:aragua')
        ->and($primary('2350'))->toBe('ve:state:guarico')
        ->and($primary('3101'))->toBe('ve:state:trujillo')
        ->and($primary('3158'))->toBe('ve:state:zulia')
        ->and($byCode->get('3101')->pluck('areaSourceId')->sort()->values()->all())->toBe(['ve:state:merida', 've:state:trujillo', 've:state:zulia']);

    // B19 r2 adds: youbianku + postcode.info per code, state-only legs.
    foreach ([['2303', 've:state:guarico'], ['2304', 've:state:guarico'], ['3060', 've:state:lara'],
        ['3102', 've:state:trujillo'], ['3108', 've:state:trujillo'], ['3113', 've:state:trujillo'],
        ['3115', 've:state:trujillo'], ['3149', 've:state:trujillo']] as [$code, $state]) {
        expect($primary($code))->toBe($state)
            ->and($byCode->get($code))->toHaveCount(1);
    }
});
