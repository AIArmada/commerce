<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\UnitedKingdom\UnitedKingdomGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 4 nations with 48 ceremonial counties, 32 councils, 22 Welsh areas, 11 NI districts', function (): void {
    $areas = app(UnitedKingdomGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'nation'))->toHaveCount(4)
        ->and($areas->where('type', 'county'))->toHaveCount(59)
        ->and($areas->where('type', 'council_area'))->toHaveCount(32)
        ->and($areas->where('type', 'county_borough'))->toHaveCount(11)
        ->and($areas->where('type', 'district'))->toHaveCount(11)
        ->and($areas->where('parentSourceId', 'gb:nation:england'))->toHaveCount(48)
        ->and($areas->where('parentSourceId', 'gb:nation:scotland'))->toHaveCount(32)
        ->and($areas->where('parentSourceId', 'gb:nation:wales'))->toHaveCount(22)
        ->and($areas->where('parentSourceId', 'gb:nation:northern-ireland'))->toHaveCount(11)
        // B13 renames: ONS LAD exact (ISO GB-ORK agrees; GB-ELS reads 'Eilean Siar';
        // the GN dump's 232 'Western Isles' rows confirm the parenthetical is legacy).
        ->and($byId->get('gb:council_area:orkney')->name)->toBe('Orkney Islands')
        ->and($byId->get('gb:council_area:na-h-eileanan-siar-western-isles')->name)->toBe('Na h-Eileanan Siar')
        // Quoted-comma NI names parse as single rows (false-alarm lines 110/118).
        ->and($byId->get('gb:district:armagh-city-banbridge-and-craigavon')->name)->toBe('Armagh City, Banbridge and Craigavon')
        ->and($byId->get('gb:district:newry-mourne-and-down')->name)->toBe('Newry, Mourne and Down');
});

it('bundles the B13-verified 2943-code outward overlay with 687 multi-L2 links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('GB', $dir . '/united-kingdom-postal-codes.csv', $dir . '/united-kingdom-postal-code-areas.csv', 'aiarmada.addressing.united_kingdom');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(3750)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(2943)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(2943)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^[A-Z]{1,2}[0-9]{1,2}[A-Z]?$/', (string) $row->code)))->toBeTrue();

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;
    $secondaries = static fn (string $code): array => $byCode->get($code)->where('isPrimary', false)->map(static fn ($row): string => (string) $row->areaSourceId)->sort()->values()->all();

    // B13 fills: E22 (Isle of Dogs, 2024+) and MK20 (MK East, 2026-01+); NSPL units +
    // mathmos/OSM pc-stats counts + postcodes.io districts (addressed sightings snippet-grade).
    expect($primary('E22'))->toBe('gb:county:greater-london')
        ->and($primary('MK20'))->toBe('gb:county:buckinghamshire');

    // Stockton-on-Tees Tees-split (statute + OSM river geometry + NSPL unit coords).
    expect($primary('TS17'))->toBe('gb:county:north-yorkshire')
        ->and($secondaries('TS17'))->toBe(['gb:county:durham'])
        ->and($primary('TS16'))->toBe('gb:county:durham')
        ->and($secondaries('TS16'))->toBe([])
        ->and($primary('TS15'))->toBe('gb:county:north-yorkshire')
        ->and($secondaries('TS15'))->toBe([])
        ->and($primary('TS2'))->toBe('gb:county:north-yorkshire')
        ->and($secondaries('TS2'))->toBe(['gb:county:durham']);

    // PA34 Highland leg was a GeoNames postcode bug (Morvern PA80 places); Argyll single.
    expect($primary('PA34'))->toBe('gb:council_area:argyll-and-bute')
        ->and($secondaries('PA34'))->toBe([]);

    // Big-flip primaries: NSPL unit plurality + GeoNames overrule the corrupt vintage links.
    expect($primary('B97'))->toBe('gb:county:worcestershire')
        ->and($primary('CV10'))->toBe('gb:county:warwickshire')
        ->and($primary('RH10'))->toBe('gb:county:west-sussex')
        ->and($primary('SG7'))->toBe('gb:county:hertfordshire')
        ->and($primary('TN2'))->toBe('gb:county:kent')
        ->and($primary('CM23'))->toBe('gb:county:hertfordshire')
        ->and($primary('DE13'))->toBe('gb:county:staffordshire')
        ->and($primary('GU12'))->toBe('gb:county:surrey')
        ->and($primary('EX23'))->toBe('gb:county:cornwall')
        ->and($primary('NP16'))->toBe('gb:county:monmouthshire');

    // City of London duals keep the Square Mile secondary under Greater London plurality.
    expect($primary('EC2P'))->toBe('gb:county:greater-london')
        ->and($secondaries('EC2P'))->toBe(['gb:county:city-of-london'])
        ->and($primary('EC1A'))->toBe('gb:county:city-of-london');

    // Scilly rolls to Cornwall; London boroughs roll to Greater London.
    expect($primary('TR25'))->toBe('gb:county:cornwall')
        ->and($primary('W1A'))->toBe('gb:county:greater-london')
        ->and($primary('E22'))->toBe('gb:county:greater-london');

    // Judgment holds: razor/thin primaries kept as bundled (see gate_holds + revisit note).
    expect($primary('NG20'))->toBe('gb:county:derbyshire')
        ->and($primary('WA3'))->toBe('gb:county:greater-manchester')
        ->and($primary('MK19'))->toBe('gb:county:buckinghamshire');

    // BT75: hold lifted 2026-10-06 — NISRA CPD_LIGHT Jul-2026 (73/136 Mid Ulster)
    // agrees with NSPL (72/136 Mid Ulster); dual leg Fermanagh and Omagh kept.
    expect($primary('BT75'))->toBe('gb:district:mid-ulster')
        ->and($secondaries('BT75'))->toBe(['gb:district:fermanagh-and-omagh']);

    // Dropped outwards stay absent (dead + Crown + ungeocoded-live classes).
    expect($byCode->has('BN91'))->toBeFalse()
        ->and($byCode->has('GIR'))->toBeFalse()
        ->and($byCode->has('E77'))->toBeFalse()
        ->and($byCode->has('W1M'))->toBeFalse()
        ->and($byCode->has('GY1'))->toBeFalse()
        ->and($byCode->has('IM99'))->toBeFalse();
});
