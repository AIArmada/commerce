<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\DominicanRepublic\DominicanRepublicGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 31 provinces plus the Distrito Nacional under regions with parent links', function (): void {
    $areas = app(DominicanRepublicGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(32)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('do:province:azua')->name)->toBe('Azua')
        ->and($byId->get('do:district:distrito-nacional')->parentSourceId)->toBe('do:region:ozama');
});

it('ships 158 municipalities under their provinces with postal locality roles', function (): void {
    $provider = app(DominicanRepublicGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'municipality'))->toHaveCount(158)
        ->and($byId->get('do:municipality:santiago-de-los-caballeros')->parentSourceId)->toBe('do:province:santiago')
        ->and($byId->get('do:municipality:higuey')->parentSourceId)->toBe('do:province:la-altagracia')
        ->and($byId->get('do:municipality:baitoa')->parentSourceId)->toBe('do:province:santiago');

    $roles = $provider->areaRoles(new AddressCountry);

    expect($roles['do:municipality:santiago-de-los-caballeros'][0]['role'])->toBe('postal_locality');
});

it('ships the B16 Quisqueya rename with ISO-exact region and province codes', function (): void {
    $areas = app(DominicanRepublicGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(200)
        ->and($byId->get('do:municipality:quisqueya')->name)->toBe('Quisqueya')
        ->and($byId->get('do:municipality:quisqueya')->parentSourceId)->toBe('do:province:san-pedro-de-macoris')
        ->and($byId->has('do:municipality:ingenio-quisqueya'))->toBeFalse()
        ->and($byId->get('do:province:baoruco')->code)->toBe('03')
        ->and($byId->get('do:region:ozama')->code)->toBe('40')
        ->and($areas->where('level', 1)->pluck('code')->sort()->values()->all())
        ->toBe(['33', '34', '35', '36', '37', '38', '39', '40', '41', '42']);
});

it('links 530 postcodes with the B16 operator-confirmed duals and GN-only exclusions', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('DO', $dir . '/dominican-republic-postal-codes.csv', $dir . '/dominican-republic-postal-code-areas.csv', 'aiarmada.addressing.dominican_republic');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(530);

    $byCode = $postcodes->groupBy->code;

    // INPOSDOM dual-lists both municipalities; population-majority primary.
    expect($byCode->get('71100')->map->areaSourceId->sort()->values()->all())
        ->toBe(['do:municipality:guayabal', 'do:municipality:pueblo-viejo'])
        ->and($byCode->get('71100')->where('isPrimary', true)->first()->areaSourceId)->toBe('do:municipality:pueblo-viejo')
        ->and($byCode->get('81100')->map->areaSourceId->sort()->values()->all())
        ->toBe(['do:municipality:cabral', 'do:municipality:jaquimeyes'])
        ->and($byCode->get('81100')->where('isPrimary', true)->first()->areaSourceId)->toBe('do:municipality:cabral');

    // GN-only DN-sector codes absent from the live operator, excluded.
    foreach (['10110', '10131', '10203', '10206', '11111', '11708'] as $bad) {
        expect($byCode->has($bad))->toBeFalse();
    }

    $primary = fn (string $code): string => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    expect($primary('21400'))->toBe('do:municipality:quisqueya')
        ->and($primary('10100'))->toBe('do:district:distrito-nacional')
        ->and($primary('56000'))->toBe('do:municipality:moca')
        ->and($primary('58081'))->toBe('do:municipality:santiago-de-los-caballeros');
});
