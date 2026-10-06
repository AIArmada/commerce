<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Italy\ItalyGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 20 regions with 109 level-2 areas and Sardinian sigla', function (): void {
    $areas = app(ItalyGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($areas->where('level', 1))->toHaveCount(20)
        ->and($l2)->toHaveCount(109)
        // B14: ISTAT Motorizzazione sigla fills.
        ->and($byId->get('it:province:gallura-north-east-sardinia')->code)->toBe('OT')
        ->and($byId->get('it:province:medio-campidano')->code)->toBe('VS')
        ->and($byId->get('it:province:ogliastra')->code)->toBe('OG')
        // Sulcis SU: CdM n.168 preliminare + n.181 definitivo + Lega press mapping
        // over stale ISTAT Feb-2026 CI (proven 2026-10-06).
        ->and($byId->get('it:province:sulcis-iglesiente')->code)->toBe('SU')
        ->and($byId->get('it:decentralization_entity:udine')->code)->toBe('UD')
        ->and($byId->get('it:province:gallura-north-east-sardinia')->parentSourceId)->toBe('it:region:sardegna')
        ->and($l2->where('parentSourceId', 'it:region:sardegna'))->toHaveCount(8);
});

it('links 4781 postcodes with the Sardinian reworks and Sappada move', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('IT', $dir . '/italy-postal-codes.csv', $dir . '/italy-postal-code-areas.csv', 'aiarmada.addressing.italy');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(4791);

    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(4781)
        ->and($byCode->filter(fn ($legs) => $legs->count() > 1))->toHaveCount(10)
        // 08020 drops the Sassari leg (ISTAT: zero Sassari-metro comuni).
        ->and($byCode->get('08020')->keyBy->areaSourceId->keys()->sort()->values()->all())
        ->toBe(['it:province:gallura-north-east-sardinia', 'it:province:nuoro'])
        ->and($byCode->get('08020')->firstWhere('isPrimary', true)->areaSourceId)->toBe('it:province:nuoro')
        // 08030 drops Oristano, Cagliari primary over Nuoro.
        ->and($byCode->get('08030')->firstWhere('isPrimary', true)->areaSourceId)->toBe('it:metropolitan_city:cagliari')
        // 09020 gains Cagliari secondary under Medio Campidano.
        ->and($byCode->get('09020')->firstWhere('isPrimary', true)->areaSourceId)->toBe('it:province:medio-campidano')
        ->and($byCode->get('09020')->keyBy->areaSourceId->has('it:metropolitan_city:cagliari'))->toBeTrue()
        // Drops + holds absent.
        ->and($byCode->has('32047'))->toBeFalse()
        ->and($byCode->has('47023'))->toBeFalse()
        ->and($byCode->has('09132'))->toBeFalse()
        ->and($byCode->has('19127'))->toBeFalse()
        // Dual-live keeps.
        ->and($byCode->get('71040')->sole()->areaSourceId)->toBe('it:province:foggia')
        // Key fills.
        ->and($byCode->get('33012')->sole()->areaSourceId)->toBe('it:decentralization_entity:udine')
        ->and($byCode->get('47521')->sole()->areaSourceId)->toBe('it:province:forli-cesena')
        ->and($byCode->get('48125')->sole()->areaSourceId)->toBe('it:province:ravenna')
        ->and($byCode->get('07051')->sole()->areaSourceId)->toBe('it:province:gallura-north-east-sardinia')
        ->and($byCode->get('09064')->sole()->areaSourceId)->toBe('it:province:ogliastra')
        ->and($byCode->get('09065')->sole()->areaSourceId)->toBe('it:province:nuoro')
        ->and($byCode->get('28925')->sole()->areaSourceId)->toBe('it:province:verbano-cusio-ossola')
        ->and($byCode->get('10079')->sole()->areaSourceId)->toBe('it:metropolitan_city:turin');
});
