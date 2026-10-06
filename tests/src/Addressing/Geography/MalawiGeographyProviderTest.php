<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Malawi\MalawiGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 28 districts under regions with parent links', function (): void {
    $areas = app(MalawiGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($l2)->toHaveCount(28)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('mw:district:balaka')->name)->toBe('Balaka')
        ->and($byId->get('mw:district:blantyre')->parentSourceId)->toBe('mw:region:southern');
});

it('bundles the gazetted 491-code 6-digit overlay with single district primaries', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MW', $dir . '/malawi-postal-codes.csv', $dir . '/malawi-postal-code-areas.csv', 'aiarmada.addressing.malawi');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(491)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(491)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(491)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{6}$/', (string) $row->code)))->toBeTrue();

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // Urban cluster anchors from the gazetted Malawi Postcodes 2019 blocks.
    expect($primary('206101'))->toBe('mw:district:lilongwe')
        ->and($primary('207201'))->toBe('mw:district:lilongwe')
        ->and($primary('207258'))->toBe('mw:district:lilongwe')
        ->and($primary('311101'))->toBe('mw:district:blantyre')
        ->and($primary('312200'))->toBe('mw:district:blantyre')
        ->and($primary('312238'))->toBe('mw:district:blantyre')
        ->and($primary('104100'))->toBe('mw:district:mzimba')
        ->and($primary('105200'))->toBe('mw:district:mzimba')
        ->and($primary('105216'))->toBe('mw:district:mzimba')
        ->and($primary('304101'))->toBe('mw:district:zomba')
        ->and($primary('305200'))->toBe('mw:district:zomba')
        ->and($primary('305219'))->toBe('mw:district:zomba');

    // Adjudicated anomalies: Lumbadzi gazetted Dowa, Luchenza maps to Thyolo,
    // Ngabu twins in Chikwawa + Nsanje, Lulanga gazette-typo row, Mavwere
    // MACRA-web-typo row, Kasungu municipality tail, Salima TA names.
    expect($primary('204108'))->toBe('mw:district:dowa')
        ->and($primary('309300'))->toBe('mw:district:thyolo')
        ->and($primary('315110'))->toBe('mw:district:chikwawa')
        ->and($primary('316106'))->toBe('mw:district:nsanje')
        ->and($primary('301100'))->toBe('mw:district:mangochi')
        ->and($primary('205113'))->toBe('mw:district:mchinji')
        ->and($primary('201308'))->toBe('mw:district:kasungu')
        ->and($primary('201311'))->toBe('mw:district:kasungu')
        ->and($primary('208103'))->toBe('mw:district:salima')
        ->and($primary('208105'))->toBe('mw:district:salima');

    // No code straddles districts: every gazetted block sits in one district.
    $multi = $byCode->filter(static fn ($rows): bool => $rows->count() > 1)->keys()->sort()->values()->all();
    expect($multi)->toBe([]);

    // Every district holds at least one code; city districts hold the most.
    $counts = $postcodes->where('isPrimary', true)->countBy('areaSourceId');
    expect($counts)->toHaveCount(28)
        ->and($counts->get('mw:district:lilongwe'))->toBe(76)
        ->and($counts->get('mw:district:blantyre'))->toBe(50)
        ->and($counts->get('mw:district:kasungu'))->toBe(39)
        ->and($counts->get('mw:district:mzimba'))->toBe(32)
        ->and($counts->get('mw:district:zomba'))->toBe(32)
        ->and($counts->get('mw:district:mangochi'))->toBe(28)
        ->and($counts->get('mw:district:thyolo'))->toBe(17)
        ->and($counts->get('mw:district:likoma'))->toBe(2);
});
