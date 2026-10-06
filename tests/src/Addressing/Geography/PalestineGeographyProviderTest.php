<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Palestine\PalestineGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Palestine tree of 16 governorates', function (): void {
    $areas = app(PalestineGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas->where('type', 'governorate'))->toHaveCount(16)
        ->and($areas->where('level', 1))->toHaveCount(16)
        ->and($areas->pluck('parentSourceId')->filter()->all())->toBe([]);

    $byId = $areas->keyBy->sourceId;

    // ISO 3166-2:PS codes; 'Jerusalem (Quds)' and 'Ramallah' are display
    // shortenings of the ISO/OCHA names (verified against OCHA COD-AB).
    expect($byId->get('ps:governorate:bethlehem')->code)->toBe('BTH')
        ->and($byId->get('ps:governorate:deir-el-balah')->code)->toBe('DEB')
        ->and($byId->get('ps:governorate:gaza')->code)->toBe('GZA')
        ->and($byId->get('ps:governorate:hebron')->code)->toBe('HBN')
        ->and($byId->get('ps:governorate:jenin')->code)->toBe('JEN')
        ->and($byId->get('ps:governorate:jericho')->code)->toBe('JRH')
        ->and($byId->get('ps:governorate:jerusalem-quds')->name)->toBe('Jerusalem (Quds)')
        ->and($byId->get('ps:governorate:jerusalem-quds')->code)->toBe('JEM')
        ->and($byId->get('ps:governorate:khan-yunis')->code)->toBe('KYS')
        ->and($byId->get('ps:governorate:nablus')->code)->toBe('NBS')
        ->and($byId->get('ps:governorate:north-gaza')->code)->toBe('NGZ')
        ->and($byId->get('ps:governorate:qalqilya')->code)->toBe('QQA')
        ->and($byId->get('ps:governorate:rafah')->code)->toBe('RFH')
        ->and($byId->get('ps:governorate:ramallah')->name)->toBe('Ramallah')
        ->and($byId->get('ps:governorate:ramallah')->code)->toBe('RBH')
        ->and($byId->get('ps:governorate:salfit')->code)->toBe('SLT')
        ->and($byId->get('ps:governorate:tubas')->code)->toBe('TBS')
        ->and($byId->get('ps:governorate:tulkarm')->code)->toBe('TKM');
});

it('bundles the 603 ministry P3 codes as single primary governorate links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('PS', $dir . '/palestine-postal-codes.csv', $dir . '/palestine-postal-code-areas.csv', 'aiarmada.addressing.palestine');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(603)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^P\d{3}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue();

    $byCode = $postcodes->keyBy->code;

    // UPU pseFr 05/2025 anchors.
    expect((string) $byCode->get('P126')->areaSourceId)->toBe('ps:governorate:jerusalem-quds')
        ->and((string) $byCode->get('P144')->areaSourceId)->toBe('ps:governorate:jerusalem-quds')
        ->and((string) $byCode->get('P610')->areaSourceId)->toBe('ps:governorate:ramallah');

    // P149 is Jerusalem, not Bethlehem: the ministry table lists Beit Safafa
    // + Sharafat under Jerusalem and legal range P100-P149 is Jerusalem, so
    // Mapanet's lone Al Walaja/Bethlehem row takes no secondary.
    expect((string) $byCode->get('P149')->areaSourceId)->toBe('ps:governorate:jerusalem-quds')
        ->and((string) $byCode->get('P150')->areaSourceId)->toBe('ps:governorate:bethlehem');

    // Legal-range boundary pins (Instruction No. 1/2022 Art. 5).
    expect((string) $byCode->get('P339')->areaSourceId)->toBe('ps:governorate:tulkarm')
        ->and((string) $byCode->get('P340')->areaSourceId)->toBe('ps:governorate:qalqilya')
        ->and((string) $byCode->get('P399')->areaSourceId)->toBe('ps:governorate:salfit')
        ->and((string) $byCode->get('P400')->areaSourceId)->toBe('ps:governorate:nablus')
        ->and((string) $byCode->get('P929')->areaSourceId)->toBe('ps:governorate:deir-el-balah')
        ->and((string) $byCode->get('P930')->areaSourceId)->toBe('ps:governorate:khan-yunis')
        ->and((string) $byCode->get('P969')->areaSourceId)->toBe('ps:governorate:khan-yunis')
        ->and((string) $byCode->get('P970')->areaSourceId)->toBe('ps:governorate:rafah');

    // Ministry per-governorate distinct counts.
    $counts = $postcodes->countBy(static fn ($row): string => (string) $row->areaSourceId);

    expect($counts->get('ps:governorate:bethlehem'))->toBe(41)
        ->and($counts->get('ps:governorate:deir-el-balah'))->toBe(7)
        ->and($counts->get('ps:governorate:gaza'))->toBe(7)
        ->and($counts->get('ps:governorate:hebron'))->toBe(86)
        ->and($counts->get('ps:governorate:jenin'))->toBe(92)
        ->and($counts->get('ps:governorate:jericho'))->toBe(20)
        ->and($counts->get('ps:governorate:jerusalem-quds'))->toBe(42)
        ->and($counts->get('ps:governorate:khan-yunis'))->toBe(5)
        ->and($counts->get('ps:governorate:nablus'))->toBe(87)
        ->and($counts->get('ps:governorate:north-gaza'))->toBe(5)
        ->and($counts->get('ps:governorate:qalqilya'))->toBe(34)
        ->and($counts->get('ps:governorate:rafah'))->toBe(4)
        ->and($counts->get('ps:governorate:ramallah'))->toBe(91)
        ->and($counts->get('ps:governorate:salfit'))->toBe(18)
        ->and($counts->get('ps:governorate:tubas'))->toBe(29)
        ->and($counts->get('ps:governorate:tulkarm'))->toBe(35);
});
