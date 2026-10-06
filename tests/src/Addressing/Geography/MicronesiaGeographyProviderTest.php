<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Micronesia\MicronesiaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 4 ISO-coded states with 75 municipalities', function (): void {
    $areas = app(MicronesiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($areas->where('type', 'state'))->toHaveCount(4)
        ->and($l2)->toHaveCount(75)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('fm:state:chuuk')->code)->toBe('TRK')
        ->and($byId->get('fm:state:kosrae')->code)->toBe('KSA')
        ->and($byId->get('fm:state:pohnpei')->code)->toBe('PNI')
        ->and($byId->get('fm:state:yap')->code)->toBe('YAP');
});

it('pins the verified FM tree of 40/4/11/20 municipalities', function (): void {
    $areas = app(MicronesiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('parentSourceId', 'fm:state:chuuk'))->toHaveCount(40)
        ->and($areas->where('parentSourceId', 'fm:state:kosrae'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'fm:state:pohnpei'))->toHaveCount(11)
        ->and($areas->where('parentSourceId', 'fm:state:yap'))->toHaveCount(20);

    // B10 holds carried forward: Tol stays a municipality (the WP
    // admin table bolds it as a city, a single unexplained signal;
    // bundle city = capital towns Weno/Kolonia only) and Utwe keeps
    // its spelling (dedicated article "Utwe (or Utwa)" + Statoids).
    expect($byId->get('fm:municipality:tol')->type)->toBe('municipality')
        ->and($byId->get('fm:city:weno')->type)->toBe('city')
        ->and($byId->get('fm:city:kolonia')->type)->toBe('city')
        ->and($byId->get('fm:municipality:utwe')->name)->toBe('Utwe');
});

it('pins the 4 state-level US ZIPs', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('FM', $dir . '/micronesia-postal-codes.csv', $dir . '/micronesia-postal-code-areas.csv', 'aiarmada.addressing.micronesia');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->keyBy->code;

    expect($postcodes)->toHaveCount(4)
        ->and($byCode->get('96941')->areaSourceId)->toBe('fm:state:pohnpei')
        ->and($byCode->get('96942')->areaSourceId)->toBe('fm:state:chuuk')
        ->and($byCode->get('96943')->areaSourceId)->toBe('fm:state:yap')
        ->and($byCode->get('96944')->areaSourceId)->toBe('fm:state:kosrae');
});
