<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Kiribati\KiribatiGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 24 councils under island groups with parent links', function (): void {
    $areas = app(KiribatiGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(24)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'ki:island:gilbert'))->toHaveCount(20)
        ->and($l2->where('parentSourceId', 'ki:island:line'))->toHaveCount(3)
        ->and($byId->get('ki:council:canton')->parentSourceId)->toBe('ki:island:phoenix')
        ->and($byId->get('ki:council:south-tarawa')->parentSourceId)->toBe('ki:island:gilbert');
});

it('pins the MICTTD/census Kanton spelling with a stable source id', function (): void {
    $areas = app(KiribatiGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    // B6 fix: MICTTD table + 2020 census (pop 41) + CLGF/MISA +
    // Factbook council lists all spell Kanton; the tree already prefers
    // I-Kiribati forms (Kiritimati over Christmas). Source id stays
    // ki:council:canton (BM saint-georges precedent); en-wiki article
    // title + airport keep Canton as the recognized alternate.
    expect($byId->get('ki:council:canton')->name)->toBe('Kanton')
        ->and($areas->where('name', 'Canton'))->toHaveCount(0);

    // Short group names held: ISO 3166-2:KI Gilbert/Line/Phoenix
    // Islands is the sole rename signal; MICTTD carries no group
    // labels and the census splits Gilbert Group vs Line Islands.
    // Codes match the ISO second parts (KI-G/L/P).
    expect($byId->get('ki:island:gilbert')->name)->toBe('Gilbert')
        ->and($byId->get('ki:island:gilbert')->code)->toBe('G')
        ->and($byId->get('ki:island:line')->name)->toBe('Line')
        ->and($byId->get('ki:island:line')->code)->toBe('L')
        ->and($byId->get('ki:island:phoenix')->name)->toBe('Phoenix')
        ->and($byId->get('ki:island:phoenix')->code)->toBe('P')
        ->and($byId->get('ki:council:banaba')->parentSourceId)->toBe('ki:island:gilbert');
});

it('bundles the 25 MICTTD inhabited-island postcodes across 25 council links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('KI', $dir . '/kiribati-postal-codes.csv', $dir . '/kiribati-postal-code-areas.csv', 'aiarmada.addressing.kiribati');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(25)
        ->and($postcodes->filter(static fn ($row): bool => $row->isPrimary))->toHaveCount(25)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(25);

    $byCode = $postcodes->groupBy->code;

    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // MICTTD island labels map 1:1 to councils: South Tarawa holds
    // both zone codes (Tangintebu-Tanaea KI0106 + Bairiki-Taborio
    // KI0107, GPO Bairiki), the Betio zone is the Betio council,
    // Tabnorth/Tabsouth are the Tabiteueas, Christmas is Kiritimati.
    expect($primary('KI0106'))->toBe('ki:council:south-tarawa')
        ->and($primary('KI0107'))->toBe('ki:council:south-tarawa')
        ->and($primary('KI0108'))->toBe('ki:council:betio')
        ->and($primary('KI0105'))->toBe('ki:council:north-tarawa')
        ->and($primary('KI0114'))->toBe('ki:council:north-tabiteuea')
        ->and($primary('KI0115'))->toBe('ki:council:south-tabiteuea')
        ->and($primary('KI0121'))->toBe('ki:council:banaba')
        ->and($primary('KI0201'))->toBe('ki:council:canton')
        ->and($primary('KI0301'))->toBe('ki:council:teraina')
        ->and($primary('KI0302'))->toBe('ki:council:tabuaeran')
        ->and($primary('KI0303'))->toBe('ki:council:kiritimati');

    // The 12 held-out codes are all uninhabited islands (en-wiki
    // explicit + absent from census Table G-1): KI0202-0208 Birnie,
    // Enderbury, Manra, McKean, Nikumaroro, Orona, Rawaki and
    // KI0304-0308 Malden, Starbuck, Millennium, Vostok, Flint. No
    // fill case; GeoNames has no KI postal export (KI.zip 404).
    foreach (['KI0202', 'KI0203', 'KI0204', 'KI0205', 'KI0206', 'KI0207', 'KI0208', 'KI0304', 'KI0305', 'KI0306', 'KI0307', 'KI0308'] as $heldOut) {
        expect($byCode->has($heldOut))->toBeFalse();
    }
});
