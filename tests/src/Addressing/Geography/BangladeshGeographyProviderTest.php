<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Bangladesh\BangladeshGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Bangladesh tree of 8 divisions and 64 districts', function (): void {
    $areas = app(BangladeshGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'division'))->toHaveCount(8)
        ->and($areas->where('type', 'district'))->toHaveCount(64)
        ->and($areas->where('parentSourceId', 'bd:division:barishal'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'bd:division:chattogram'))->toHaveCount(11)
        ->and($areas->where('parentSourceId', 'bd:division:dhaka'))->toHaveCount(13)
        ->and($areas->where('parentSourceId', 'bd:division:khulna'))->toHaveCount(10)
        ->and($areas->where('parentSourceId', 'bd:division:mymensingh'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'bd:division:rajshahi'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'bd:division:rangpur'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'bd:division:sylhet'))->toHaveCount(4);

    // ISO 3166-2:BD oracle: division letters A-H, district codes 01-64.
    expect($byId->get('bd:division:barishal')->code)->toBe('A')
        ->and($byId->get('bd:division:mymensingh')->code)->toBe('H')
        ->and($byId->get('bd:district:bandarban')->code)->toBe('01')
        ->and($byId->get('bd:district:thakurgaon')->code)->toBe('64')
        ->and($byId->get('bd:district:thakurgaon')->parentSourceId)->toBe('bd:division:rangpur')
        ->and($byId->get('bd:district:chapai-nawabganj')->parentSourceId)->toBe('bd:division:rajshahi');

    // Post-2018 spellings; BD-41 deliberately Netrokona (official portal +
    // Districts oracle) against lagging ISO Netrakona.
    expect($byId->get('bd:district:bogura')->name)->toBe('Bogura')
        ->and($byId->get('bd:district:jashore')->name)->toBe('Jashore')
        ->and($byId->get('bd:district:chattogram')->name)->toBe('Chattogram')
        ->and($byId->get('bd:district:cumilla')->name)->toBe('Cumilla')
        ->and($byId->get('bd:district:netrokona')->name)->toBe('Netrokona')
        ->and($byId->get('bd:district:jhalakathi')->name)->toBe('Jhalakathi');
});

it('bundles the verified 1373-code overlay with exact primaries and fills', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('BD', $dir . '/bangladesh-postal-codes.csv', $dir . '/bangladesh-postal-code-areas.csv', 'aiarmada.addressing.bangladesh');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(1373)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(1373)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(1373)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{4}$/', (string) $row->code)))->toBeTrue();

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // GPO anchors: UPU addressing profile + official finder agree.
    expect($primary('1000'))->toBe('bd:district:dhaka')
        ->and($primary('1100'))->toBe('bd:district:dhaka')
        ->and($primary('4000'))->toBe('bd:district:chattogram')
        ->and($primary('6000'))->toBe('bd:district:rajshahi')
        ->and($primary('9000'))->toBe('bd:district:khulna');

    // Cross-block keeps: the official finder confirms these live outside
    // their district home block (Lohajong 133x, Chhatak 3893, Pirganj
    // 5470, Gopalganj Sadar 8013); Demra 1360 is official-confirmed.
    expect($primary('1333'))->toBe('bd:district:munshiganj')
        ->and($primary('1334'))->toBe('bd:district:munshiganj')
        ->and($primary('1335'))->toBe('bd:district:munshiganj')
        ->and($primary('3893'))->toBe('bd:district:sunamganj')
        ->and($primary('5470'))->toBe('bd:district:thakurgaon')
        ->and($primary('8013'))->toBe('bd:district:gopalganj')
        ->and($primary('1360'))->toBe('bd:district:dhaka');

    // Post-2018 fills: Mapanet + postcodebase + GPO directory (2024
    // official Cumilla page for 3505/3512/3547/3573).
    expect($primary('1236'))->toBe('bd:district:dhaka')
        ->and($primary('1706'))->toBe('bd:district:gazipur')
        ->and($primary('2206'))->toBe('bd:district:mymensingh')
        ->and($primary('2207'))->toBe('bd:district:mymensingh')
        ->and($primary('2208'))->toBe('bd:district:mymensingh')
        ->and($primary('3118'))->toBe('bd:district:sylhet')
        ->and($primary('3505'))->toBe('bd:district:cumilla')
        ->and($primary('3512'))->toBe('bd:district:cumilla')
        ->and($primary('3547'))->toBe('bd:district:cumilla')
        ->and($primary('3573'))->toBe('bd:district:cumilla')
        ->and($primary('3726'))->toBe('bd:district:lakshmipur')
        ->and($primary('3727'))->toBe('bd:district:lakshmipur')
        ->and($primary('3805'))->toBe('bd:district:noakhali')
        ->and($primary('3813'))->toBe('bd:district:noakhali')
        ->and($primary('4357'))->toBe('bd:district:chattogram')
        ->and($primary('4640'))->toBe('bd:district:bandarban')
        ->and($primary('6200'))->toBe('bd:district:rajshahi')
        ->and($primary('6207'))->toBe('bd:district:rajshahi')
        ->and($primary('6213'))->toBe('bd:district:rajshahi')
        ->and($primary('6433'))->toBe('bd:district:natore')
        ->and($primary('6501'))->toBe('bd:district:naogaon')
        ->and($primary('8207'))->toBe('bd:district:barishal')
        ->and($primary('8217'))->toBe('bd:district:barishal')
        ->and($primary('8611'))->toBe('bd:district:patuakhali');

    // Rejected lookalikes stay absent: Mapanet-lineage typos (1661-1665,
    // 2461, 4240, 8920/8921, 1218, 1231, 1921) and superseded 3515.
    foreach (['1661', '1662', '1663', '1664', '1665', '2461', '4240', '8920', '8921', '1218', '1231', '1921', '3515'] as $code) {
        expect($byCode->has($code))->toBeFalse();
    }
});
