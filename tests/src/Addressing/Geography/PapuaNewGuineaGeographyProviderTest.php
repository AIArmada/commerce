<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\PapuaNewGuinea\PapuaNewGuineaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 22 first-level units and 96 districts with parent links', function (): void {
    $areas = app(PapuaNewGuineaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('level', 1))->toHaveCount(22)
        ->and($areas->where('level', 2))->toHaveCount(96)
        ->and($byId->get('pg:district:bulolo')->parentSourceId)->toBe('pg:province:morobe');
});

it('names the Morobe district Bulolo without the article suffix', function (): void {
    $areas = app(PapuaNewGuineaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    // WP canonical title is "Bulolo District"; Bulolo_District was an artifact.
    expect($byId->get('pg:district:bulolo')->name)->toBe('Bulolo')
        ->and($byId->has('pg:district:bulolo-district'))->toBeFalse();
});

it('pins the five Post PNG office fills', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('PG', $dir . '/papua-new-guinea-postal-codes.csv', $dir . '/papua-new-guinea-postal-code-areas.csv', 'aiarmada.addressing.papua_new_guinea');

    $postcodes = $source->postalCodes()->collect();
    $single = static fn (string $code): string => $postcodes->where('code', $code)->sole()->areaSourceId;

    // Gordons (PNGEC 2022 schedule: under NORTH-EAST header), Tabubil (North
    // Fly LLG table), DWU (Madang seat town), Kokopo + Lihir (seat / island).
    expect($single('135'))->toBe('pg:district:port-moresby-north-east')
        ->and($single('332'))->toBe('pg:district:north-fly')
        ->and($single('512'))->toBe('pg:district:madang')
        ->and($single('613'))->toBe('pg:district:kokopo')
        ->and($single('635'))->toBe('pg:district:namatanai');
});

it('pins the four multi-district codes and their primaries', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('PG', $dir . '/papua-new-guinea-postal-codes.csv', $dir . '/papua-new-guinea-postal-code-areas.csv', 'aiarmada.addressing.papua_new_guinea');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode->get('136')->count())->toBe(2)
        ->and($byCode->get('136')->firstWhere('isPrimary', true)->areaSourceId)->toBe('pg:district:port-moresby-north-west')
        ->and($byCode->get('293')->count())->toBe(2)
        ->and($byCode->get('293')->firstWhere('isPrimary', true)->areaSourceId)->toBe('pg:district:wapenamanda')
        ->and($byCode->get('355')->count())->toBe(3)
        ->and($byCode->get('355')->firstWhere('isPrimary', true)->areaSourceId)->toBe('pg:district:north-bougainville')
        ->and($byCode->get('461')->count())->toBe(6)
        ->and($byCode->get('461')->firstWhere('isPrimary', true)->areaSourceId)->toBe('pg:district:kundiawa-gembogl');
});
