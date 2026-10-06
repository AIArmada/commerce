<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Belgium\BelgiumGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Belgium tree of 3 regions and 10 provinces', function (): void {
    $areas = app(BelgiumGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'region'))->toHaveCount(3)
        ->and($areas->where('type', 'province'))->toHaveCount(10)
        ->and($areas->where('level', 1))->toHaveCount(3)
        ->and($areas->where('level', 2))->toHaveCount(10)
        ->and($areas->where('parentSourceId', 'be:region:flanders'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'be:region:wallonia'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'be:region:brussels-capital'))->toHaveCount(0);

    // ISO 3166-2:BE codes, exact set (Brussels-Capital is the
    // conventional English name; Liège keeps its accent).
    expect($byId->get('be:region:brussels-capital')->code)->toBe('BRU')
        ->and($byId->get('be:region:flanders')->code)->toBe('VLG')
        ->and($byId->get('be:region:wallonia')->code)->toBe('WAL')
        ->and($byId->get('be:province:antwerp')->code)->toBe('VAN')
        ->and($byId->get('be:province:east-flanders')->code)->toBe('VOV')
        ->and($byId->get('be:province:flemish-brabant')->code)->toBe('VBR')
        ->and($byId->get('be:province:limburg')->code)->toBe('VLI')
        ->and($byId->get('be:province:west-flanders')->code)->toBe('VWV')
        ->and($byId->get('be:province:walloon-brabant')->code)->toBe('WBR')
        ->and($byId->get('be:province:hainaut')->code)->toBe('WHT')
        ->and($byId->get('be:province:liege')->code)->toBe('WLG')
        ->and($byId->get('be:province:liege')->name)->toBe('Liège')
        ->and($byId->get('be:province:luxembourg')->code)->toBe('WLX')
        ->and($byId->get('be:province:namur')->code)->toBe('WNA')
        ->and($byId->get('be:province:antwerp')->parentSourceId)->toBe('be:region:flanders')
        ->and($byId->get('be:province:liege')->parentSourceId)->toBe('be:region:wallonia')
        ->and($byId->get('be:province:walloon-brabant')->parentSourceId)->toBe('be:region:wallonia');
});

it('bundles the 1146 Belgian postcodes as single primaries', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('BE', $dir . '/belgium-postal-codes.csv', $dir . '/belgium-postal-code-areas.csv', 'aiarmada.addressing.belgium');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(1146)
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue();

    $byCode = $postcodes->keyBy->code;

    // B4 seat anchors (all Nominatim-corroborated).
    expect((string) $byCode->get('1000')->areaSourceId)->toBe('be:region:brussels-capital')
        ->and((string) $byCode->get('2000')->areaSourceId)->toBe('be:province:antwerp')
        ->and((string) $byCode->get('3000')->areaSourceId)->toBe('be:province:flemish-brabant')
        ->and((string) $byCode->get('4000')->areaSourceId)->toBe('be:province:liege')
        ->and((string) $byCode->get('5000')->areaSourceId)->toBe('be:province:namur')
        ->and((string) $byCode->get('6000')->areaSourceId)->toBe('be:province:hainaut')
        ->and((string) $byCode->get('7000')->areaSourceId)->toBe('be:province:hainaut')
        ->and((string) $byCode->get('8000')->areaSourceId)->toBe('be:province:west-flanders')
        ->and((string) $byCode->get('9000')->areaSourceId)->toBe('be:province:east-flanders')
        ->and((string) $byCode->get('1300')->areaSourceId)->toBe('be:province:walloon-brabant')
        ->and((string) $byCode->get('3500')->areaSourceId)->toBe('be:province:limburg')
        ->and((string) $byCode->get('6700')->areaSourceId)->toBe('be:province:luxembourg');

    // Exclave keeps: Voeren/Fourons Limburg, Comines-Warneton and
    // Mouscron Hainaut (GeoNames admin2 + Nominatim agree).
    expect((string) $byCode->get('3790')->areaSourceId)->toBe('be:province:limburg')
        ->and((string) $byCode->get('3793')->areaSourceId)->toBe('be:province:limburg')
        ->and((string) $byCode->get('3798')->areaSourceId)->toBe('be:province:limburg')
        ->and((string) $byCode->get('7780')->areaSourceId)->toBe('be:province:hainaut')
        ->and((string) $byCode->get('7784')->areaSourceId)->toBe('be:province:hainaut')
        ->and((string) $byCode->get('7700')->areaSourceId)->toBe('be:province:hainaut')
        ->and((string) $byCode->get('7712')->areaSourceId)->toBe('be:province:hainaut');

    // Brussels-periphery and language-border keeps: the 1xxx range is
    // genuinely split across three areas, never cross-linked per code.
    expect((string) $byCode->get('1640')->areaSourceId)->toBe('be:province:flemish-brabant')
        ->and((string) $byCode->get('3080')->areaSourceId)->toBe('be:province:flemish-brabant')
        ->and((string) $byCode->get('1420')->areaSourceId)->toBe('be:province:walloon-brabant')
        ->and((string) $byCode->get('1330')->areaSourceId)->toBe('be:province:walloon-brabant')
        ->and((string) $byCode->get('9600')->areaSourceId)->toBe('be:province:east-flanders')
        ->and((string) $byCode->get('7750')->areaSourceId)->toBe('be:province:hainaut');

    // Held out: institutional specials (EU/NATO/broadcasters/parliaments
    // reserved numbers; GN omits and Nominatim has no areas for them).
    expect($byCode->has('1005'))->toBeFalse()
        ->and($byCode->has('1010'))->toBeFalse()
        ->and($byCode->has('1044'))->toBeFalse()
        ->and($byCode->has('1045'))->toBeFalse()
        ->and($byCode->has('1047'))->toBeFalse()
        ->and($byCode->has('1048'))->toBeFalse()
        ->and($byCode->has('1049'))->toBeFalse();

    $counts = $postcodes->countBy(static fn ($row): string => (string) $row->areaSourceId);

    expect($counts)->toHaveCount(11)
        ->and($counts->get('be:province:hainaut'))->toBe(210)
        ->and($counts->get('be:province:liege'))->toBe(158)
        ->and($counts->get('be:province:flemish-brabant'))->toBe(128)
        ->and($counts->get('be:province:east-flanders'))->toBe(113)
        ->and($counts->get('be:province:west-flanders'))->toBe(104)
        ->and($counts->get('be:province:antwerp'))->toBe(102)
        ->and($counts->get('be:province:luxembourg'))->toBe(101)
        ->and($counts->get('be:province:namur'))->toBe(92)
        ->and($counts->get('be:province:limburg'))->toBe(72)
        ->and($counts->get('be:province:walloon-brabant'))->toBe(44)
        ->and($counts->get('be:region:brussels-capital'))->toBe(22);
});
