<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Bahrain\BahrainGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Bahrain tree of 4 governorates', function (): void {
    $areas = app(BahrainGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas->where('type', 'governorate'))->toHaveCount(4)
        ->and($areas->where('level', 1))->toHaveCount(4)
        ->and($areas->pluck('parentSourceId')->filter()->all())->toBe([]);

    $byId = $areas->keyBy->sourceId;

    // ISO 3166-2:BH codes (Central BH-16 abolished 2014).
    expect($byId->get('bh:governorate:capital')->code)->toBe('13')
        ->and($byId->get('bh:governorate:southern')->code)->toBe('14')
        ->and($byId->get('bh:governorate:muharraq')->code)->toBe('15')
        ->and($byId->get('bh:governorate:northern')->code)->toBe('17');
});

it('bundles the 479 block postcodes as single primary governorate links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('BH', $dir . '/bahrain-postal-codes.csv', $dir . '/bahrain-postal-code-areas.csv', 'aiarmada.addressing.bahrain');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(479)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{3,4}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue()
        ->and($postcodes->pluck('code')->unique())->toHaveCount(479);

    $byCode = $postcodes->keyBy->code;

    // UPU BHR profile anchors: AL-MANAMAH 317, RIFFA 926.
    expect((string) $byCode->get('317')->areaSourceId)->toBe('bh:governorate:capital')
        ->and((string) $byCode->get('926')->areaSourceId)->toBe('bh:governorate:southern');

    // Addressed-sighting anchors: Sanabis 408, Riffa 915, Nasfa 733, Sanad 743.
    expect((string) $byCode->get('408')->areaSourceId)->toBe('bh:governorate:capital')
        ->and((string) $byCode->get('915')->areaSourceId)->toBe('bh:governorate:southern')
        ->and((string) $byCode->get('733')->areaSourceId)->toBe('bh:governorate:capital')
        ->and((string) $byCode->get('743')->areaSourceId)->toBe('bh:governorate:capital');

    // A'ali-area three-way split (straddles governorate boundaries).
    expect((string) $byCode->get('732')->areaSourceId)->toBe('bh:governorate:northern')
        ->and((string) $byCode->get('746')->areaSourceId)->toBe('bh:governorate:southern')
        ->and((string) $byCode->get('748')->areaSourceId)->toBe('bh:governorate:southern');

    // Per-governorate block counts.
    $counts = $postcodes->countBy(static fn ($row): string => (string) $row->areaSourceId);

    expect($counts->get('bh:governorate:capital'))->toBe(121)
        ->and($counts->get('bh:governorate:muharraq'))->toBe(74)
        ->and($counts->get('bh:governorate:northern'))->toBe(156)
        ->and($counts->get('bh:governorate:southern'))->toBe(128);
});
