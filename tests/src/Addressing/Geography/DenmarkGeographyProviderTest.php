<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Denmark\DenmarkGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('names region 84 Capital Region with a Hovedstaden alias', function (): void {
    $areas = app(DenmarkGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;
    $capital = $areas->get('dk:region:capital-region');

    expect($capital->name)->toBe('Capital Region')
        ->and($capital->code)->toBe('84');

    $names = app(DenmarkGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names['dk:region:capital-region'][0]['name'])->toBe('Hovedstaden');
});

it('pins the verified Denmark tree of 5 regions and 98 municipalities', function (): void {
    $areas = app(DenmarkGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(103)
        ->and($areas->where('type', 'region'))->toHaveCount(5)
        ->and($areas->where('type', 'municipality'))->toHaveCount(98)
        ->and($areas->where('level', 1))->toHaveCount(5)
        ->and($areas->where('level', 2))->toHaveCount(98);

    // ISO 3166-2:DK region codes, exact set (unchanged since the 2007 reform).
    expect($areas->where('level', 1)->pluck('code')->sort()->values()->all())->toBe(
        ['81', '82', '83', '84', '85']
    );

    // Per-region municipality membership: Capital 29 / South 22 / Central 19 / Zealand 17 / North 11.
    expect($areas->where('level', 2)->countBy('parentSourceId')->sortKeys()->all())->toBe([
        'dk:region:capital-region' => 29,
        'dk:region:central-denmark' => 19,
        'dk:region:north-denmark' => 11,
        'dk:region:southern-denmark' => 22,
        'dk:region:zealand' => 17,
    ]);

    // Post-2007 roster spots: 260 carries the current Halsnæs name (renamed
    // from Frederiksværk-Hundested 2008); Bornholm sits in the Capital Region;
    // Ertholmene/Christiansø is correctly absent (Ministry of Defence land).
    expect($byId->get('dk:municipality:halsnaes')->code)->toBe('260')
        ->and($byId->get('dk:municipality:halsnaes')->name)->toBe('Halsnæs')
        ->and($byId->get('dk:municipality:bornholm')->parentSourceId)->toBe('dk:region:capital-region')
        ->and($byId->get('dk:municipality:copenhagen')->code)->toBe('101')
        ->and($byId->get('dk:municipality:aero')->name)->toBe('Ærø')
        ->and($areas->first(static fn ($area): bool => str_contains((string) $area->sourceId, 'christians')))->toBeNull();
});

it('bundles the 1159 Danish postcodes as single-municipality links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('DK', $dir . '/denmark-postal-codes.csv', $dir . '/denmark-postal-code-areas.csv', 'aiarmada.addressing.denmark');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(1159)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(1159)
        ->and($postcodes->where('isPrimary', false))->toHaveCount(0)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(1159)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{4}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->pluck('code')->min())->toBe('0800')
        ->and($postcodes->pluck('code')->max())->toBe('9990');

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // Special codes joined to the operating municipality (GeoNames scope).
    expect($primary('0800'))->toBe('dk:municipality:hoje-taastrup')
        ->and($primary('0900'))->toBe('dk:municipality:copenhagen')
        ->and($primary('0917'))->toBe('dk:municipality:brondby')
        ->and($primary('0960'))->toBe('dk:municipality:copenhagen')
        ->and($primary('0999'))->toBe('dk:municipality:copenhagen')
        ->and($primary('1513'))->toBe('dk:municipality:copenhagen');

    // da-WP-stale keeps, live per the fresh GeoNames dump + OSM boundaries.
    expect($primary('1311'))->toBe('dk:municipality:copenhagen')
        ->and($primary('4942'))->toBe('dk:municipality:lolland')
        ->and($primary('5943'))->toBe('dk:municipality:langeland')
        ->and($primary('8981'))->toBe('dk:municipality:randers')
        ->and($primary('8983'))->toBe('dk:municipality:randers');

    // Reinstated islands (Agersø/Omø/Femø, 2017) and tiny-island districts.
    expect($primary('4244'))->toBe('dk:municipality:slagelse')
        ->and($primary('4245'))->toBe('dk:municipality:slagelse')
        ->and($primary('4945'))->toBe('dk:municipality:lolland')
        ->and($primary('5602'))->toBe('dk:municipality:faaborg-midtfyn')
        ->and($primary('5965'))->toBe('dk:municipality:aero')
        ->and($primary('6210'))->toBe('dk:municipality:aabenraa');

    // Outlet, service, terminal and company codes stay out of the overlay,
    // as do Greenland 39xx / Faroe 38xx (separate countries) and the
    // unassigned 10xx-19xx block reserves.
    foreach (['0555', '0704', '0801', '1000', '1027', '1395', '1566', '2412', '3761', '3800', '3900', '9999'] as $excluded) {
        expect($byCode->has($excluded))->toBeFalse();
    }

    // Anchor districts: every municipality keeps at least one code.
    expect($postcodes->pluck('areaSourceId')->unique())->toHaveCount(98);
});
