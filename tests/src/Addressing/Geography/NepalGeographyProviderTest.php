<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Nepal\NepalGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 7 ISO provinces with 77 federal districts and parent links', function (): void {
    $areas = app(NepalGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($areas->where('level', 1))->toHaveCount(7)
        ->and($l2)->toHaveCount(77)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('np:province:koshi')->code)->toBe('P1')
        ->and($byId->get('np:province:madhesh')->code)->toBe('P2')
        ->and($byId->get('np:province:bagmati')->code)->toBe('P3')
        ->and($byId->get('np:province:gandaki')->code)->toBe('P4')
        ->and($byId->get('np:province:lumbini')->code)->toBe('P5')
        ->and($byId->get('np:province:karnali')->code)->toBe('P6')
        ->and($byId->get('np:province:sudurpashchim')->code)->toBe('P7');
});

it('pins the verified NP tree of 14/8/13/11/12/10/9 districts', function (): void {
    $areas = app(NepalGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('parentSourceId', 'np:province:koshi'))->toHaveCount(14)
        ->and($areas->where('parentSourceId', 'np:province:madhesh'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'np:province:bagmati'))->toHaveCount(13)
        ->and($areas->where('parentSourceId', 'np:province:gandaki'))->toHaveCount(11)
        ->and($areas->where('parentSourceId', 'np:province:lumbini'))->toHaveCount(12)
        ->and($areas->where('parentSourceId', 'np:province:karnali'))->toHaveCount(10)
        ->and($areas->where('parentSourceId', 'np:province:sudurpashchim'))->toHaveCount(9);

    // Federal splits: Nawalparasi West (Parasi) + Nawalpur, Rukum E/W.
    expect($byId->get('np:district:nawalpur')->parentSourceId)->toBe('np:province:gandaki')
        ->and($byId->get('np:district:nawalparasi-west-of-bardaghat-susta')->parentSourceId)->toBe('np:province:lumbini')
        ->and($byId->get('np:district:eastern-rukum')->parentSourceId)->toBe('np:province:lumbini')
        ->and($byId->get('np:district:western-rukum')->parentSourceId)->toBe('np:province:karnali');
});

it('bundles the B11-verified 753-code GPO palika overlay with zero duals', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('NP', $dir . '/nepal-postal-codes.csv', $dir . '/nepal-postal-code-areas.csv', 'aiarmada.addressing.nepal');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(753)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(753)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(753)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^[1-7]\\d{4}$/', (string) $row->code)))->toBeTrue();

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // Block-edge anchors across all 7 provinces (GPO official table).
    expect($primary('10101'))->toBe('np:district:taplejung')
        ->and($primary('10109'))->toBe('np:district:taplejung')
        ->and($primary('11408'))->toBe('np:district:udayapur')
        ->and($primary('20118'))->toBe('np:district:saptari')
        ->and($primary('30601'))->toBe('np:district:kathmandu')
        ->and($primary('30611'))->toBe('np:district:kathmandu')
        ->and($primary('31307'))->toBe('np:district:chitwan')
        ->and($primary('40505'))->toBe('np:district:kaski')
        ->and($primary('40808'))->toBe('np:district:nawalpur')
        ->and($primary('50103'))->toBe('np:district:eastern-rukum')
        ->and($primary('50707'))->toBe('np:district:nawalparasi-west-of-bardaghat-susta')
        ->and($primary('60806'))->toBe('np:district:western-rukum')
        ->and($primary('61009'))->toBe('np:district:surkhet')
        ->and($primary('70909'))->toBe('np:district:kanchanpur');

    // Old 1991-system codes correctly absent (Kathmandu 44600 etc.).
    expect($byCode->has('44600'))->toBeFalse()
        ->and($byCode->has('33700'))->toBeFalse()
        ->and($byCode->has('10100'))->toBeFalse();

    // Zero duals: every code links exactly once, all primary.
    $multi = $byCode->filter(static fn ($rows): bool => $rows->count() > 1)->keys()->all();
    expect($multi)->toBe([]);

    // Per-district primary counts pin the GPO blocks.
    $counts = $postcodes->where('isPrimary', true)->countBy('areaSourceId');
    expect($counts->get('np:district:taplejung'))->toBe(9)
        ->and($counts->get('np:district:morang'))->toBe(17)
        ->and($counts->get('np:district:sarlahi'))->toBe(20)
        ->and($counts->get('np:district:kathmandu'))->toBe(11)
        ->and($counts->get('np:district:kaski'))->toBe(5)
        ->and($counts->get('np:district:nawalpur'))->toBe(8)
        ->and($counts->get('np:district:eastern-rukum'))->toBe(3)
        ->and($counts->get('np:district:rupandehi'))->toBe(16)
        ->and($counts->get('np:district:kailali'))->toBe(13);
});
