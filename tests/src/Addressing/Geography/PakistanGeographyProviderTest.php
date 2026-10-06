<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Pakistan\PakistanGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Pakistan tree of 4 provinces, 3 territories, 178 districts', function (): void {
    $areas = app(PakistanGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas)->toHaveCount(185)
        ->and($areas->where('type', 'province'))->toHaveCount(4)
        ->and($areas->where('type', 'territory'))->toHaveCount(3)
        ->and($areas->where('type', 'district'))->toHaveCount(178)
        ->and($areas->where('level', 1))->toHaveCount(7)
        ->and($areas->where('level', 2))->toHaveCount(178);

    $byId = $areas->keyBy->sourceId;

    // ISO 3166-2:PK codes.
    expect($byId->get('pk:province:balochistan')->code)->toBe('BA')
        ->and($byId->get('pk:territory:gilgit-baltistan')->code)->toBe('GB')
        ->and($byId->get('pk:territory:islamabad')->code)->toBe('IS')
        ->and($byId->get('pk:territory:azad-jammu-and-kashmir')->code)->toBe('JK')
        ->and($byId->get('pk:province:khyber-pakhtunkhwa')->code)->toBe('KP')
        ->and($byId->get('pk:province:punjab')->code)->toBe('PB')
        ->and($byId->get('pk:province:sindh')->code)->toBe('SD');

    // Per-parent district counts incl the May 2026 Balochistan batch and
    // the 2022 Punjab/KP/GB splits.
    $l2 = $areas->where('level', 2);
    $expected = [
        'pk:province:balochistan' => 42,
        'pk:province:khyber-pakhtunkhwa' => 40,
        'pk:province:punjab' => 41,
        'pk:province:sindh' => 30,
        'pk:territory:azad-jammu-and-kashmir' => 10,
        'pk:territory:gilgit-baltistan' => 14,
        'pk:territory:islamabad' => 1,
    ];

    foreach ($expected as $parent => $count) {
        expect($l2->where('parentSourceId', $parent))->toHaveCount($count, $parent);
    }
});

it('bundles the 3114 Pakistan Post codes as 3121 district links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('PK', $dir . '/pakistan-postal-codes.csv', $dir . '/pakistan-postal-code-areas.csv', 'aiarmada.addressing.pakistan');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(3121)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(3114)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(3114)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{5}$/', (string) $row->code)))->toBeTrue();

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // UPU PAK profile + GPO-headquarter anchors.
    expect($primary('44000'))->toBe('pk:district:islamabad') // ISLAMABAD GPO
        ->and($primary('54000'))->toBe('pk:district:lahore') // LAHORE GPO
        ->and($primary('25000'))->toBe('pk:district:peshawar') // PESHAWAR GPO
        ->and($primary('87300'))->toBe('pk:district:quetta-east') // QUETTA GPO
        ->and($primary('75600'))->toBe('pk:district:karachi-south') // KARACHI CLIFTON
        ->and($primary('03816'))->toBe('pk:district:faisalabad') // PARTAB NAGAR SO (NPO series)
        ->and($primary('80510'))->toBe('pk:district:jafarabad') // Goth Jia Khan (Circular 4/2022)
        ->and($primary('16810'))->toBe('pk:district:shigar'); // PION (Skardu GPO account)

    // The 7 directory-attested dual links, primaries kept as verified.
    $secondary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', false)->areaSourceId;

    expect($byCode->get('02502'))->toHaveCount(2)
        ->and($primary('02502'))->toBe('pk:district:mirpur')
        ->and($secondary('02502'))->toBe('pk:district:peshawar')
        ->and($primary('06011'))->toBe('pk:district:multan')
        ->and($secondary('06011'))->toBe('pk:district:vehari')
        ->and($primary('06536'))->toBe('pk:district:khairpur')
        ->and($secondary('06536'))->toBe('pk:district:sukkur')
        ->and($primary('07418'))->toBe('pk:district:karachi-south')
        ->and($secondary('07418'))->toBe('pk:district:malir')
        ->and($primary('07514'))->toBe('pk:district:keamari')
        ->and($secondary('07514'))->toBe('pk:district:korangi')
        ->and($primary('07529'))->toBe('pk:district:malir')
        ->and($secondary('07529'))->toBe('pk:district:karachi-east')
        ->and($primary('24302'))->toBe('pk:district:nowshera')
        ->and($secondary('24302'))->toBe('pk:district:peshawar');

    // 167 districts covered; the 11 codeless districts have no delivery
    // or NPO office in any Pakistan Post source.
    $counts = $postcodes->where('isPrimary', true)->countBy(static fn ($row): string => (string) $row->areaSourceId);

    expect($counts)->toHaveCount(167)
        ->and($counts->get('pk:district:lahore'))->toBe(163)
        ->and($counts->get('pk:district:rawalpindi'))->toBe(151)
        ->and($counts->get('pk:district:faisalabad'))->toBe(92)
        ->and($counts->get('pk:district:shigar'))->toBe(1);

    foreach (['pk:district:allai', 'pk:district:darel', 'pk:district:haveli', 'pk:district:kolai-palas', 'pk:district:lower-south-waziristan', 'pk:district:mohmand', 'pk:district:roundu', 'pk:district:sohbatpur', 'pk:district:surab', 'pk:district:upper-dera-bugti', 'pk:district:wadh'] as $codeless) {
        expect($counts->has($codeless))->toBeFalse($codeless);
    }
});
