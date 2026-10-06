<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Cambodia\CambodiaGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('roles Phnom Penh as a province and krong cities as districts', function (): void {
    $roles = app(CambodiaGeographyProvider::class)->areaRoles(new AddressCountry);

    expect($roles['kh:municipality:phnom-penh'][0]['role'])->toBe('province')
        ->and($roles['kh:municipality:poipet'][0]['role'])->toBe('district')
        ->and($roles['kh:district:mongkol-borey'][0]['role'])->toBe('district')
        ->and($roles['kh:section:chamkar-mon'][0]['role'])->toBe('district');
});

it('ships 210 NIS-coded districts with the B16 Samraong split', function (): void {
    $areas = app(CambodiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(235)
        ->and($areas->where('level', '2'))->toHaveCount(210)
        ->and($byId->get('kh:municipality:samraong')->code)->toBe('2204')
        ->and($byId->get('kh:municipality:samraong')->parentSourceId)->toBe('kh:province:oddar-meanchey')
        ->and($byId->get('kh:district:samraong')->code)->toBe('2107')
        ->and($byId->get('kh:district:samraong')->parentSourceId)->toBe('kh:province:takeo')
        ->and($byId->get('kh:section:kamboul')->code)->toBe('1214')
        ->and($byId->get('kh:municipality:bokor')->code)->toBe('0709');
});

it('links 1633 commune postcodes with the B16 Samraong remap and remap blocks', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('KH', $dir . '/cambodia-postal-codes.csv', $dir . '/cambodia-postal-code-areas.csv', 'aiarmada.addressing.cambodia');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(1633);

    $byCode = $postcodes->groupBy->code;

    // 240401-05 are Oddar Meanchey district-04 communes (NIS 220401-05), not Takeo's.
    foreach (['240401', '240402', '240403', '240404', '240405'] as $code) {
        expect($byCode->get($code)->first()->areaSourceId)->toBe('kh:municipality:samraong');
    }

    // Stale NIS-2009 code, Ou Krasar gap, and district-base codes excluded.
    foreach (['141006', '220102', '120100', '070900', '230200'] as $bad) {
        expect($byCode->has($bad))->toBeFalse();
    }

    $primary = fn (string $code): string => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    expect($primary('210701'))->toBe('kh:district:samraong')
        ->and($primary('060705'))->toBe('kh:district:santuk')
        ->and($primary('120209'))->toBe('kh:section:doun-penh')
        ->and($primary('220101'))->toBe('kh:district:damnak-chang-aeur')
        ->and($primary('230201'))->toBe('kh:district:sala-krau');
});
