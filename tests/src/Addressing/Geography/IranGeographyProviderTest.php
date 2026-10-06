<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Iran\IranGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('exposes the corrected West Azerbaijan province slug and name', function (): void {
    $areas = app(IranGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('ir:province:west-azerbaijan')->name)->toBe('West Azerbaijan')
        ->and($areas->get('ir:province:west-azerbaijan')->code)->toBe('04')
        ->and($areas->get('ir:province:west-azerbaijan')->type)->toBe('province')
        ->and($areas->has('ir:province:west-azarbaijan'))->toBeFalse();
});

it('ships 429 May-2019 counties under provinces with parent links', function (): void {
    $areas = app(IranGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(429)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'ir:province:tehran'))->toHaveCount(16)
        ->and($l2->where('parentSourceId', 'ir:province:qom'))->toHaveCount(1)
        ->and($byId->get('ir:county:abadan')->parentSourceId)->toBe('ir:province:khuzestan');

    // Full per-province county table (COD v01 May-2019 exact).
    $counts = $l2->countBy('parentSourceId');

    expect($counts->get('ir:province:markazi'))->toBe(12)
        ->and($counts->get('ir:province:gilan'))->toBe(16)
        ->and($counts->get('ir:province:mazandaran'))->toBe(22)
        ->and($counts->get('ir:province:east-azerbaijan'))->toBe(20)
        ->and($counts->get('ir:province:west-azerbaijan'))->toBe(17)
        ->and($counts->get('ir:province:kermanshah'))->toBe(14)
        ->and($counts->get('ir:province:khuzestan'))->toBe(27)
        ->and($counts->get('ir:province:fars'))->toBe(29)
        ->and($counts->get('ir:province:kerman'))->toBe(23)
        ->and($counts->get('ir:province:razavi-khorasan'))->toBe(28)
        ->and($counts->get('ir:province:isfahan'))->toBe(24)
        ->and($counts->get('ir:province:sistan-and-baluchestan'))->toBe(19)
        ->and($counts->get('ir:province:kurdistan'))->toBe(10)
        ->and($counts->get('ir:province:hamadan'))->toBe(9)
        ->and($counts->get('ir:province:chaharmahal-and-bakhtiari'))->toBe(9)
        ->and($counts->get('ir:province:lorestan'))->toBe(11)
        ->and($counts->get('ir:province:ilam'))->toBe(10)
        ->and($counts->get('ir:province:kohgiluyeh-and-boyer-ahmad'))->toBe(8)
        ->and($counts->get('ir:province:bushehr'))->toBe(10)
        ->and($counts->get('ir:province:zanjan'))->toBe(8)
        ->and($counts->get('ir:province:semnan'))->toBe(8)
        ->and($counts->get('ir:province:yazd'))->toBe(10)
        ->and($counts->get('ir:province:hormozgan'))->toBe(13)
        ->and($counts->get('ir:province:ardabil'))->toBe(10)
        ->and($counts->get('ir:province:qazvin'))->toBe(6)
        ->and($counts->get('ir:province:golestan'))->toBe(14)
        ->and($counts->get('ir:province:north-khorasan'))->toBe(8)
        ->and($counts->get('ir:province:south-khorasan'))->toBe(11)
        ->and($counts->get('ir:province:alborz'))->toBe(6);
});

it('pins the 31 ISO 3166-2:IR province codes', function (): void {
    $byId = app(IranGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    // IR-09 is formally Khorasan-e Razavi (the wiki "Central Khorasan"
    // English gloss is unofficial); the rest match the en names exactly.
    expect($byId->get('ir:province:markazi')->code)->toBe('00')
        ->and($byId->get('ir:province:gilan')->code)->toBe('01')
        ->and($byId->get('ir:province:mazandaran')->code)->toBe('02')
        ->and($byId->get('ir:province:east-azerbaijan')->code)->toBe('03')
        ->and($byId->get('ir:province:kermanshah')->code)->toBe('05')
        ->and($byId->get('ir:province:khuzestan')->code)->toBe('06')
        ->and($byId->get('ir:province:fars')->code)->toBe('07')
        ->and($byId->get('ir:province:kerman')->code)->toBe('08')
        ->and($byId->get('ir:province:razavi-khorasan')->code)->toBe('09')
        ->and($byId->get('ir:province:isfahan')->code)->toBe('10')
        ->and($byId->get('ir:province:sistan-and-baluchestan')->code)->toBe('11')
        ->and($byId->get('ir:province:kurdistan')->code)->toBe('12')
        ->and($byId->get('ir:province:hamadan')->code)->toBe('13')
        ->and($byId->get('ir:province:chaharmahal-and-bakhtiari')->code)->toBe('14')
        ->and($byId->get('ir:province:lorestan')->code)->toBe('15')
        ->and($byId->get('ir:province:ilam')->code)->toBe('16')
        ->and($byId->get('ir:province:kohgiluyeh-and-boyer-ahmad')->code)->toBe('17')
        ->and($byId->get('ir:province:bushehr')->code)->toBe('18')
        ->and($byId->get('ir:province:zanjan')->code)->toBe('19')
        ->and($byId->get('ir:province:semnan')->code)->toBe('20')
        ->and($byId->get('ir:province:yazd')->code)->toBe('21')
        ->and($byId->get('ir:province:hormozgan')->code)->toBe('22')
        ->and($byId->get('ir:province:tehran')->code)->toBe('23')
        ->and($byId->get('ir:province:ardabil')->code)->toBe('24')
        ->and($byId->get('ir:province:qom')->code)->toBe('25')
        ->and($byId->get('ir:province:qazvin')->code)->toBe('26')
        ->and($byId->get('ir:province:golestan')->code)->toBe('27')
        ->and($byId->get('ir:province:north-khorasan')->code)->toBe('28')
        ->and($byId->get('ir:province:south-khorasan')->code)->toBe('29')
        ->and($byId->get('ir:province:alborz')->code)->toBe('30');
});

it('links the 109 Mapanet 5-digit codes to provinces with exactly one primary each', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('IR', $dir . '/iran-postal-codes.csv', $dir . '/iran-postal-code-areas.csv', 'aiarmada.addressing.iran');

    $postcodes = $source->postalCodes()->collect();

    // 109 codes / 111 links: the 45617 + 97716 duals.
    expect($postcodes)->toHaveCount(111)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(109)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{5}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->every(static fn ($row): bool => $row->relationshipType === 'served_by'))->toBeTrue()
        ->and($postcodes->where('isPrimary', true))->toHaveCount(109)
        ->and($postcodes->where('isPrimary', true)->pluck('code')->unique())->toHaveCount(109);

    $primary = $postcodes->where('isPrimary', true)->keyBy->code;

    // M7 move: 96914 is Gonabad city (Razavi Khorasan), not South Khorasan.
    expect((string) $primary->get('96914')->areaSourceId)->toBe('ir:province:razavi-khorasan');

    // Khorasan misfile keeps: Bojnurd rows under the Razavi r1, Ferdows
    // and Sarayan rows under the North Khorasan r1.
    expect((string) $primary->get('94614')->areaSourceId)->toBe('ir:province:north-khorasan')
        ->and((string) $primary->get('94714')->areaSourceId)->toBe('ir:province:north-khorasan')
        ->and((string) $primary->get('97716')->areaSourceId)->toBe('ir:province:south-khorasan')
        ->and((string) $primary->get('96714')->areaSourceId)->toBe('ir:province:south-khorasan')
        ->and((string) $primary->get('97614')->areaSourceId)->toBe('ir:province:south-khorasan');

    // Same-name-row keeps: the Sari row sits in Pakdasht (Tehran), the
    // Nahavand row in Takestan (Qazvin), Arzuiyeh rows in Kerman.
    expect((string) $primary->get('11369')->areaSourceId)->toBe('ir:province:tehran')
        ->and((string) $primary->get('33134')->areaSourceId)->toBe('ir:province:tehran')
        ->and((string) $primary->get('45617')->areaSourceId)->toBe('ir:province:qazvin')
        ->and((string) $primary->get('78514')->areaSourceId)->toBe('ir:province:kerman')
        ->and((string) $primary->get('37184')->areaSourceId)->toBe('ir:province:qom');

    // Duals: Abhar-area rows share 45617 with Qazvin; Gazi/Jazin rows
    // (Jazin RD, Bajestan) share 97716 with South Khorasan.
    $secondaries = $postcodes->where('isPrimary', false);

    expect($secondaries)->toHaveCount(2)
        ->and((string) $secondaries->where('code', '45617')->first()->areaSourceId)->toBe('ir:province:zanjan')
        ->and((string) $secondaries->where('code', '97716')->first()->areaSourceId)->toBe('ir:province:razavi-khorasan');

    // Per-province primary counts (Alborz codeless: no Mapanet r1).
    $counts = $primary->countBy(static fn ($row): string => (string) $row->areaSourceId);

    expect($counts->get('ir:province:hamadan'))->toBe(11)
        ->and($counts->get('ir:province:kermanshah'))->toBe(10)
        ->and($counts->get('ir:province:ardabil'))->toBe(9)
        ->and($counts->get('ir:province:mazandaran'))->toBe(7)
        ->and($counts->get('ir:province:lorestan'))->toBe(7)
        ->and($counts->get('ir:province:hormozgan'))->toBe(7)
        ->and($counts->get('ir:province:kurdistan'))->toBe(6)
        ->and($counts->get('ir:province:ilam'))->toBe(4)
        ->and($counts->get('ir:province:markazi'))->toBe(3)
        ->and($counts->get('ir:province:gilan'))->toBe(3)
        ->and($counts->get('ir:province:khuzestan'))->toBe(3)
        ->and($counts->get('ir:province:fars'))->toBe(3)
        ->and($counts->get('ir:province:isfahan'))->toBe(3)
        ->and($counts->get('ir:province:chaharmahal-and-bakhtiari'))->toBe(3)
        ->and($counts->get('ir:province:bushehr'))->toBe(3)
        ->and($counts->get('ir:province:golestan'))->toBe(3)
        ->and($counts->get('ir:province:south-khorasan'))->toBe(3)
        ->and($counts->get('ir:province:east-azerbaijan'))->toBe(2)
        ->and($counts->get('ir:province:west-azerbaijan'))->toBe(2)
        ->and($counts->get('ir:province:kerman'))->toBe(2)
        ->and($counts->get('ir:province:zanjan'))->toBe(2)
        ->and($counts->get('ir:province:semnan'))->toBe(2)
        ->and($counts->get('ir:province:tehran'))->toBe(2)
        ->and($counts->get('ir:province:qom'))->toBe(2)
        ->and($counts->get('ir:province:north-khorasan'))->toBe(2)
        ->and($counts->get('ir:province:razavi-khorasan'))->toBe(1)
        ->and($counts->get('ir:province:sistan-and-baluchestan'))->toBe(1)
        ->and($counts->get('ir:province:kohgiluyeh-and-boyer-ahmad'))->toBe(1)
        ->and($counts->get('ir:province:yazd'))->toBe(1)
        ->and($counts->get('ir:province:qazvin'))->toBe(1)
        ->and($counts->has('ir:province:alborz'))->toBeFalse();
});
