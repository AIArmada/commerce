<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Iraq\IraqGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Iraq tree of 19 governorates and 119 districts', function (): void {
    $areas = app(IraqGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas->where('type', 'governorate'))->toHaveCount(19)
        ->and($areas->where('type', 'district'))->toHaveCount(119)
        ->and($areas->where('level', 1))->toHaveCount(19)
        ->and($areas->where('level', 2))->toHaveCount(119);

    $byId = $areas->keyBy->sourceId;

    // ISO 3166-2:IQ codes (18 governorates; KR is a region, not a row).
    expect($byId->get('iq:governorate:al-anbar')->code)->toBe('AN')
        ->and($byId->get('iq:governorate:basra')->code)->toBe('BA')
        ->and($byId->get('iq:governorate:al-muthanna')->code)->toBe('MU')
        ->and($byId->get('iq:governorate:al-qadisiyyah')->code)->toBe('QA')
        ->and($byId->get('iq:governorate:najaf')->code)->toBe('NA')
        ->and($byId->get('iq:governorate:erbil')->code)->toBe('AR')
        ->and($byId->get('iq:governorate:sulaymaniyah')->code)->toBe('SU')
        ->and($byId->get('iq:governorate:babylon')->code)->toBe('BB')
        ->and($byId->get('iq:governorate:baghdad')->code)->toBe('BG')
        ->and($byId->get('iq:governorate:dohuk')->code)->toBe('DA')
        ->and($byId->get('iq:governorate:dhi-qar')->code)->toBe('DQ')
        ->and($byId->get('iq:governorate:diyala')->code)->toBe('DI')
        ->and($byId->get('iq:governorate:karbala')->code)->toBe('KA')
        ->and($byId->get('iq:governorate:kirkuk')->code)->toBe('KI')
        ->and($byId->get('iq:governorate:maysan')->code)->toBe('MA')
        ->and($byId->get('iq:governorate:nineveh')->code)->toBe('NI')
        ->and($byId->get('iq:governorate:saladin')->code)->toBe('SD')
        ->and($byId->get('iq:governorate:wasit')->code)->toBe('WA')
        ->and($byId->get('iq:governorate:halabja')->code)->toBe('HL');

    // Makhmur sits under Nineveh only (contested Erbil claim rejected).
    expect($byId->get('iq:district:makhmur')->parentSourceId)->toBe('iq:governorate:nineveh');

    // Per-governorate district counts.
    $counts = $areas->where('level', 2)->countBy('parentSourceId');

    expect($counts->get('iq:governorate:sulaymaniyah'))->toBe(13)
        ->and($counts->get('iq:governorate:baghdad'))->toBe(10)
        ->and($counts->get('iq:governorate:al-anbar'))->toBe(8)
        ->and($counts->get('iq:governorate:nineveh'))->toBe(8)
        ->and($counts->get('iq:governorate:saladin'))->toBe(8)
        ->and($counts->get('iq:governorate:erbil'))->toBe(8)
        ->and($counts->get('iq:governorate:dohuk'))->toBe(7)
        ->and($counts->get('iq:governorate:basra'))->toBe(6)
        ->and($counts->get('iq:governorate:diyala'))->toBe(6)
        ->and($counts->get('iq:governorate:maysan'))->toBe(6)
        ->and($counts->get('iq:governorate:wasit'))->toBe(6)
        ->and($counts->get('iq:governorate:dhi-qar'))->toBe(5)
        ->and($counts->get('iq:governorate:halabja'))->toBe(5)
        ->and($counts->get('iq:governorate:al-muthanna'))->toBe(4)
        ->and($counts->get('iq:governorate:al-qadisiyyah'))->toBe(4)
        ->and($counts->get('iq:governorate:babylon'))->toBe(4)
        ->and($counts->get('iq:governorate:kirkuk'))->toBe(4)
        ->and($counts->get('iq:governorate:najaf'))->toBe(4)
        ->and($counts->get('iq:governorate:karbala'))->toBe(3);
});

it('bundles the 348 town postcodes as single primary governorate links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('IQ', $dir . '/iraq-postal-codes.csv', $dir . '/iraq-postal-code-areas.csv', 'aiarmada.addressing.iraq');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(348)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{5}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue()
        ->and($postcodes->pluck('code')->unique())->toHaveCount(348);

    $byCode = $postcodes->keyBy->code;

    // UPU irqEn anchor: 61002 Basra office delivery.
    expect((string) $byCode->get('61002')->areaSourceId)->toBe('iq:governorate:basra');

    // M5 in-block corrections.
    expect((string) $byCode->get('10080')->areaSourceId)->toBe('iq:governorate:baghdad')
        ->and((string) $byCode->get('46016')->areaSourceId)->toBe('iq:governorate:sulaymaniyah')
        ->and((string) $byCode->get('46005')->areaSourceId)->toBe('iq:governorate:sulaymaniyah')
        ->and((string) $byCode->get('46007')->areaSourceId)->toBe('iq:governorate:sulaymaniyah')
        ->and((string) $byCode->get('46008')->areaSourceId)->toBe('iq:governorate:sulaymaniyah')
        ->and((string) $byCode->get('46006')->areaSourceId)->toBe('iq:governorate:halabja')
        ->and((string) $byCode->get('58014')->areaSourceId)->toBe('iq:governorate:al-qadisiyyah');

    // Cross-block keeps: Baghdad-district towns, Makhmur towns, Koya towns.
    expect((string) $byCode->get('31020')->areaSourceId)->toBe('iq:governorate:baghdad')
        ->and((string) $byCode->get('34004')->areaSourceId)->toBe('iq:governorate:baghdad')
        ->and((string) $byCode->get('44015')->areaSourceId)->toBe('iq:governorate:nineveh')
        ->and((string) $byCode->get('44020')->areaSourceId)->toBe('iq:governorate:nineveh')
        ->and((string) $byCode->get('44021')->areaSourceId)->toBe('iq:governorate:nineveh')
        ->and((string) $byCode->get('44022')->areaSourceId)->toBe('iq:governorate:kirkuk')
        ->and((string) $byCode->get('46015')->areaSourceId)->toBe('iq:governorate:erbil')
        ->and((string) $byCode->get('46017')->areaSourceId)->toBe('iq:governorate:erbil')
        ->and((string) $byCode->get('46022')->areaSourceId)->toBe('iq:governorate:diyala')
        ->and((string) $byCode->get('58012')->areaSourceId)->toBe('iq:governorate:wasit');

    // Disputed-territory ties held at Nineveh.
    expect((string) $byCode->get('42004')->areaSourceId)->toBe('iq:governorate:nineveh')
        ->and((string) $byCode->get('42012')->areaSourceId)->toBe('iq:governorate:nineveh')
        ->and((string) $byCode->get('44018')->areaSourceId)->toBe('iq:governorate:nineveh')
        ->and((string) $byCode->get('44016')->areaSourceId)->toBe('iq:governorate:nineveh');

    // Per-governorate block counts.
    $counts = $postcodes->countBy(static fn ($row): string => (string) $row->areaSourceId);

    expect($counts->get('iq:governorate:baghdad'))->toBe(73)
        ->and($counts->get('iq:governorate:basra'))->toBe(30)
        ->and($counts->get('iq:governorate:nineveh'))->toBe(32)
        ->and($counts->get('iq:governorate:al-anbar'))->toBe(21)
        ->and($counts->get('iq:governorate:diyala'))->toBe(20)
        ->and($counts->get('iq:governorate:babylon'))->toBe(19)
        ->and($counts->get('iq:governorate:erbil'))->toBe(18)
        ->and($counts->get('iq:governorate:sulaymaniyah'))->toBe(18)
        ->and($counts->get('iq:governorate:al-qadisiyyah'))->toBe(17)
        ->and($counts->get('iq:governorate:dhi-qar'))->toBe(16)
        ->and($counts->get('iq:governorate:kirkuk'))->toBe(16)
        ->and($counts->get('iq:governorate:saladin'))->toBe(13)
        ->and($counts->get('iq:governorate:maysan'))->toBe(12)
        ->and($counts->get('iq:governorate:wasit'))->toBe(12)
        ->and($counts->get('iq:governorate:dohuk'))->toBe(10)
        ->and($counts->get('iq:governorate:al-muthanna'))->toBe(8)
        ->and($counts->get('iq:governorate:najaf'))->toBe(7)
        ->and($counts->get('iq:governorate:karbala'))->toBe(4)
        ->and($counts->get('iq:governorate:halabja'))->toBe(2);
});
