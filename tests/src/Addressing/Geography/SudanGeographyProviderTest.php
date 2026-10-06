<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Sudan\SudanGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Sudan tree of 18 states and 188 districts', function (): void {
    $areas = app(SudanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(206)
        ->and($areas->where('type', 'state'))->toHaveCount(18)
        ->and($areas->where('type', 'district'))->toHaveCount(188)
        ->and($areas->where('parentSourceId', 'sd:state:al-jazirah'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'sd:state:al-qadarif'))->toHaveCount(12)
        ->and($areas->where('parentSourceId', 'sd:state:blue-nile'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'sd:state:central-darfur'))->toHaveCount(9)
        ->and($areas->where('parentSourceId', 'sd:state:east-darfur'))->toHaveCount(9)
        ->and($areas->where('parentSourceId', 'sd:state:kassala'))->toHaveCount(11)
        ->and($areas->where('parentSourceId', 'sd:state:khartoum'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'sd:state:north-darfur'))->toHaveCount(17)
        ->and($areas->where('parentSourceId', 'sd:state:north-kordofan'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'sd:state:northern'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'sd:state:red-sea'))->toHaveCount(10)
        ->and($areas->where('parentSourceId', 'sd:state:river-nile'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'sd:state:sennar'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'sd:state:south-darfur'))->toHaveCount(21)
        ->and($areas->where('parentSourceId', 'sd:state:south-kordofan'))->toHaveCount(17)
        ->and($areas->where('parentSourceId', 'sd:state:west-darfur'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'sd:state:west-kordofan'))->toHaveCount(14)
        ->and($areas->where('parentSourceId', 'sd:state:white-nile'))->toHaveCount(9);

    // ISO 3166-2:SD codes.
    expect($byId->get('sd:state:red-sea')->code)->toBe('RS')
        ->and($byId->get('sd:state:al-jazirah')->code)->toBe('GZ')
        ->and($byId->get('sd:state:khartoum')->code)->toBe('KH')
        ->and($byId->get('sd:state:al-qadarif')->code)->toBe('GD')
        ->and($byId->get('sd:state:white-nile')->code)->toBe('NW')
        ->and($byId->get('sd:state:blue-nile')->code)->toBe('NB')
        ->and($byId->get('sd:state:northern')->code)->toBe('NO')
        ->and($byId->get('sd:state:river-nile')->code)->toBe('NR')
        ->and($byId->get('sd:state:sennar')->code)->toBe('SI')
        ->and($byId->get('sd:state:north-kordofan')->code)->toBe('KN')
        ->and($byId->get('sd:state:south-kordofan')->code)->toBe('KS')
        ->and($byId->get('sd:state:west-kordofan')->code)->toBe('GK')
        ->and($byId->get('sd:state:north-darfur')->code)->toBe('DN')
        ->and($byId->get('sd:state:south-darfur')->code)->toBe('DS')
        ->and($byId->get('sd:state:west-darfur')->code)->toBe('DW')
        ->and($byId->get('sd:state:east-darfur')->code)->toBe('DE')
        ->and($byId->get('sd:state:central-darfur')->code)->toBe('DC')
        ->and($byId->get('sd:state:kassala')->code)->toBe('KA');

    // Abyei PCA row excluded as disputed; the Abyei district ships under West Kordofan.
    expect($areas->first(static fn ($a): bool => $a->name === 'Abyei')->parentSourceId)
        ->toBe('sd:state:west-kordofan')
        ->and($areas->first(static fn ($a): bool => str_contains($a->name, 'PCA')))->toBeNull();
});

it('pins the verified Sudan dual-code map: 90 codes over 97 links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('SD', $dir . '/sudan-postal-codes.csv', $dir . '/sudan-postal-code-areas.csv', 'aiarmada.addressing.sudan');

    $links = $source->postalCodes()->collect();

    expect($links)->toHaveCount(97)
        ->and($links->pluck('code')->unique())->toHaveCount(90)
        ->and($links->every(static fn ($row): bool => (bool) preg_match('/^\\d{5}$/', (string) $row->code)))->toBeTrue()
        ->and($links->where('isPrimary', true)->pluck('code')->unique())->toHaveCount(90);

    $byCode = $links->groupBy->code;

    // M6 fix: 13315 is Khartoum-single (SCC directory + OSM border evidence).
    expect($byCode->get('13315'))->toHaveCount(1)
        ->and($byCode->get('13315')->first()->areaSourceId)->toBe('sd:state:khartoum')
        ->and($byCode->get('13315')->first()->isPrimary)->toBeTrue();

    // UPU SDN anchor: 11111 Khartoum.
    expect((string) $byCode->get('11111')->first()->areaSourceId)->toBe('sd:state:khartoum');

    // The 7 remaining duals with SCC-corroborated primaries.
    $dual21115 = $byCode->get('21115');
    expect($dual21115->pluck('areaSourceId')->sort()->values()->all())
        ->toBe(['sd:state:al-jazirah', 'sd:state:sennar'])
        ->and($dual21115->where('isPrimary', true)->pluck('areaSourceId')->all())->toBe(['sd:state:al-jazirah']);

    expect($byCode->get('25514')->where('isPrimary', true)->pluck('areaSourceId')->all())->toBe(['sd:state:blue-nile'])
        ->and($byCode->get('31116')->where('isPrimary', true)->pluck('areaSourceId')->all())->toBe(['sd:state:al-qadarif'])
        ->and($byCode->get('51111')->where('isPrimary', true)->pluck('areaSourceId')->all())->toBe(['sd:state:north-kordofan'])
        ->and($byCode->get('51113')->where('isPrimary', true)->pluck('areaSourceId')->all())->toBe(['sd:state:north-kordofan'])
        ->and($byCode->get('52221')->where('isPrimary', true)->pluck('areaSourceId')->all())->toBe(['sd:state:north-kordofan'])
        ->and($byCode->get('63314')->where('isPrimary', true)->pluck('areaSourceId')->all())->toBe(['sd:state:west-darfur'])
        ->and($byCode->get('63314')->where('isPrimary', false)->pluck('areaSourceId')->all())->toBe(['sd:state:central-darfur']);

    // East Darfur codeless; the other 17 states covered.
    expect($links->pluck('areaSourceId')->unique()->sort()->values()->all())->toHaveCount(17)
        ->and($links->pluck('areaSourceId')->contains('sd:state:east-darfur'))->toBeFalse();

    // Per-state primary counts.
    $primaries = $links->where('isPrimary', true)->countBy(static fn ($row): string => (string) $row->areaSourceId);

    expect($primaries->get('sd:state:al-jazirah'))->toBe(11)
        ->and($primaries->get('sd:state:northern'))->toBe(8)
        ->and($primaries->get('sd:state:red-sea'))->toBe(8)
        ->and($primaries->get('sd:state:al-qadarif'))->toBe(7)
        ->and($primaries->get('sd:state:kassala'))->toBe(7)
        ->and($primaries->get('sd:state:north-kordofan'))->toBe(7)
        ->and($primaries->get('sd:state:khartoum'))->toBe(6)
        ->and($primaries->get('sd:state:west-kordofan'))->toBe(6)
        ->and($primaries->get('sd:state:blue-nile'))->toBe(5)
        ->and($primaries->get('sd:state:sennar'))->toBe(5)
        ->and($primaries->get('sd:state:river-nile'))->toBe(4)
        ->and($primaries->get('sd:state:south-kordofan'))->toBe(4)
        ->and($primaries->get('sd:state:north-darfur'))->toBe(3)
        ->and($primaries->get('sd:state:south-darfur'))->toBe(3)
        ->and($primaries->get('sd:state:west-darfur'))->toBe(3)
        ->and($primaries->get('sd:state:white-nile'))->toBe(3);
});
