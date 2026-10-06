<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Montenegro\MontenegroGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships the 25 ISO 3166-2 municipalities as a flat level-1 tier', function (): void {
    $areas = app(MontenegroGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(25)
        ->and($areas->where('type', 'municipality'))->toHaveCount(25)
        ->and($areas->pluck('code')->sort()->values()->all())->toBe([
            '01', '02', '03', '04', '05', '06', '07', '08', '09', '10',
            '11', '12', '13', '14', '15', '16', '17', '18', '19', '20',
            '21', '22', '23', '24', '25',
        ])
        ->and($byId->get('me:municipality:gusinje')->code)->toBe('22')
        ->and($byId->get('me:municipality:petnjica')->code)->toBe('23')
        ->and($byId->get('me:municipality:tuzi')->code)->toBe('24')
        ->and($byId->get('me:municipality:zeta')->code)->toBe('25')
        ->and($areas->whereNotNull('parentSourceId'))->toBeEmpty();
});

it('pins the official Old Royal Capital name for ME-06', function (): void {
    $areas = app(MontenegroGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    // Official Prijestonica style; ISO's short "Cetinje" is the outlier.
    expect($byId->get('me:municipality:old-royal-capital-cetinje')->name)->toBe('Old Royal Capital Cetinje')
        ->and($byId->get('me:municipality:old-royal-capital-cetinje')->code)->toBe('06');
});

it('bundles the 149 Pošta CG postcodes across 150 municipality links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('ME', $dir . '/montenegro-postal-codes.csv', $dir . '/montenegro-postal-code-areas.csv', 'aiarmada.addressing.montenegro');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(150)
        ->and($postcodes->filter(static fn ($row): bool => $row->isPrimary))->toHaveCount(149);

    $byCode = $postcodes->groupBy->code;

    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // 85333 is genuinely shared: the Lepetane office (Tivat, Dec 2023)
    // holds the primary on overwhelming addressed usage, while Pošta's
    // own Jun-2023 notice names "pošta 85333 Dobrota 2" (Kotor).
    expect($primary('85333'))->toBe('me:municipality:tivat')
        ->and($byCode->get('85333'))->toHaveCount(2)
        ->and($byCode->get('85333')->firstWhere('isPrimary', false)->areaSourceId)->toBe('me:municipality:kotor');

    // UPU mne profile anchors, incl. the 81205 UBLI rural example.
    expect($primary('81000'))->toBe('me:municipality:podgorica')
        ->and($primary('85000'))->toBe('me:municipality:bar')
        ->and($primary('84000'))->toBe('me:municipality:bijelo-polje')
        ->and($primary('85330'))->toBe('me:municipality:kotor')
        ->and($primary('81205'))->toBe('me:municipality:podgorica');

    // Post-split municipalities keep their own delivery codes.
    expect($primary('81206'))->toBe('me:municipality:tuzi')
        ->and($primary('81304'))->toBe('me:municipality:zeta')
        ->and($primary('84326'))->toBe('me:municipality:gusinje')
        ->and($primary('84312'))->toBe('me:municipality:petnjica');

    // Bay-of-Kotor neighbours around the shared code stay single-homed.
    expect($primary('85331'))->toBe('me:municipality:kotor')
        ->and($primary('85332'))->toBe('me:municipality:tivat')
        ->and($primary('85334'))->toBe('me:municipality:kotor')
        ->and($byCode->get('85331'))->toHaveCount(1);

    // Pin-overrule keeps: the 84216 branch pin lands 85 km off in
    // Nikšić but Kovačevići village is Pljevlja; the 85317 pin sits
    // ~350 m across the border in Podlastva (Budva) while Lastva
    // Grbaljska village (tagged 85317) is Kotor.
    expect($primary('84216'))->toBe('me:municipality:pljevlja')
        ->and($primary('85317'))->toBe('me:municipality:kotor');

    // Service, retired, and stale codes stay out of the bundle.
    $codes = file($dir . '/montenegro-postal-codes.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    expect($codes)->toContain('ME,81000')
        ->and($codes)->not->toContain('ME,80000')
        ->and($codes)->not->toContain('ME,81122')
        ->and($codes)->not->toContain('ME,81125')
        ->and($codes)->not->toContain('ME,81128')
        ->and($codes)->not->toContain('ME,85354')
        ->and($codes)->not->toContain('ME,85357')
        ->and($codes)->not->toContain('ME,81201');
});
