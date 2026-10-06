<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\SaudiArabia\SaudiArabiaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Saudi tree of 13 regions and 139 governorates', function (): void {
    $areas = app(SaudiArabiaGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas->where('type', 'region'))->toHaveCount(13)
        ->and($areas->where('type', 'governorate'))->toHaveCount(139)
        ->and($areas->where('level', 1))->toHaveCount(13)
        ->and($areas->where('level', 2))->toHaveCount(139);

    $byId = $areas->keyBy->sourceId;

    // ISO 3166-2:SA codes (no SA-13).
    expect($byId->get('sa:region:riyadh')->code)->toBe('01')
        ->and($byId->get('sa:region:makkah')->code)->toBe('02')
        ->and($byId->get('sa:region:al-madinah')->code)->toBe('03')
        ->and($byId->get('sa:region:eastern-province')->code)->toBe('04')
        ->and($byId->get('sa:region:al-qassim')->code)->toBe('05')
        ->and($byId->get('sa:region:ha-il')->code)->toBe('06')
        ->and($byId->get('sa:region:tabuk')->code)->toBe('07')
        ->and($byId->get('sa:region:northern-borders')->code)->toBe('08')
        ->and($byId->get('sa:region:jizan')->code)->toBe('09')
        ->and($byId->get('sa:region:najran')->code)->toBe('10')
        ->and($byId->get('sa:region:al-bahah')->code)->toBe('11')
        ->and($byId->get('sa:region:al-jawf')->code)->toBe('12')
        ->and($byId->get('sa:region:asir')->code)->toBe('14');

    // Per-region governorate counts (Wikipedia Governorates list).
    $govCounts = $areas->where('level', 2)->countBy(static fn ($row): string => (string) $row->parentSourceId);

    expect($govCounts->get('sa:region:riyadh'))->toBe(22)
        ->and($govCounts->get('sa:region:makkah'))->toBe(16)
        ->and($govCounts->get('sa:region:al-madinah'))->toBe(8)
        ->and($govCounts->get('sa:region:eastern-province'))->toBe(12)
        ->and($govCounts->get('sa:region:al-qassim'))->toBe(13)
        ->and($govCounts->get('sa:region:ha-il'))->toBe(8)
        ->and($govCounts->get('sa:region:tabuk'))->toBe(6)
        ->and($govCounts->get('sa:region:northern-borders'))->toBe(3)
        ->and($govCounts->get('sa:region:jizan'))->toBe(16)
        ->and($govCounts->get('sa:region:najran'))->toBe(6)
        ->and($govCounts->get('sa:region:al-bahah'))->toBe(9)
        ->and($govCounts->get('sa:region:al-jawf'))->toBe(3)
        ->and($govCounts->get('sa:region:asir'))->toBe(17);

    // Zero orphans: every governorate parent is a shipped region.
    $regionIds = $areas->where('level', 1)->pluck('sourceId')->all();

    expect($areas->where('level', 2)->every(
        static fn ($row): bool => in_array($row->parentSourceId, $regionIds, true)
    ))->toBeTrue();
});

it('bundles the 9256 Wasel bases as single primary region links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('SA', $dir . '/saudi-arabia-postal-codes.csv', $dir . '/saudi-arabia-postal-code-areas.csv', 'aiarmada.addressing.saudi_arabia');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(9256)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{5}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue()
        ->and($postcodes->pluck('code')->unique())->toHaveCount(9256);

    // 11xxx is P.O.-Box space (UPU SAU profile's own POB example is
    // 11564), correctly absent from this Wasel home-delivery set.
    expect($postcodes->first(static fn ($row): bool => str_starts_with((string) $row->code, '11')))->toBeNull();

    $byCode = $postcodes->keyBy->code;

    // Adjudication anchors (OSM-reverse verdicts, see gate_sa.py).
    expect((string) $byCode->get('28769')->areaSourceId)->toBe('sa:region:al-bahah')
        ->and((string) $byCode->get('58459')->areaSourceId)->toBe('sa:region:riyadh')
        ->and((string) $byCode->get('58276')->areaSourceId)->toBe('sa:region:al-qassim')
        ->and((string) $byCode->get('65457')->areaSourceId)->toBe('sa:region:makkah')
        ->and((string) $byCode->get('65495')->areaSourceId)->toBe('sa:region:makkah')
        ->and((string) $byCode->get('89973')->areaSourceId)->toBe('sa:region:asir')
        ->and((string) $byCode->get('89799')->areaSourceId)->toBe('sa:region:jizan')
        ->and((string) $byCode->get('89934')->areaSourceId)->toBe('sa:region:jizan');

    // M5 flips: 8636x Samtah block Madinah->Jizan.
    expect((string) $byCode->get('86365')->areaSourceId)->toBe('sa:region:jizan')
        ->and((string) $byCode->get('86366')->areaSourceId)->toBe('sa:region:jizan')
        ->and((string) $byCode->get('86369')->areaSourceId)->toBe('sa:region:jizan');

    // Per-region primary counts (post-flip).
    $counts = $postcodes->countBy(static fn ($row): string => (string) $row->areaSourceId);

    expect($counts->get('sa:region:riyadh'))->toBe(943)
        ->and($counts->get('sa:region:makkah'))->toBe(1868)
        ->and($counts->get('sa:region:al-madinah'))->toBe(651)
        ->and($counts->get('sa:region:eastern-province'))->toBe(724)
        ->and($counts->get('sa:region:al-qassim'))->toBe(966)
        ->and($counts->get('sa:region:ha-il'))->toBe(745)
        ->and($counts->get('sa:region:tabuk'))->toBe(177)
        ->and($counts->get('sa:region:northern-borders'))->toBe(65)
        ->and($counts->get('sa:region:jizan'))->toBe(948)
        ->and($counts->get('sa:region:najran'))->toBe(193)
        ->and($counts->get('sa:region:al-bahah'))->toBe(374)
        ->and($counts->get('sa:region:al-jawf'))->toBe(384)
        ->and($counts->get('sa:region:asir'))->toBe(1218);
});

it('strips Wasel building suffixes to the 5-digit base', function (): void {
    $provider = app(SaudiArabiaGeographyProvider::class);

    expect($provider->postalCodeLookupKeys('12273-1234'))->toBe(['12273-1234', '12273'])
        ->and($provider->postalCodeLookupKeys('122731234'))->toBe(['122731234', '12273'])
        ->and($provider->postalCodeLookupKeys('12273'))->toBe(['12273']);
});
