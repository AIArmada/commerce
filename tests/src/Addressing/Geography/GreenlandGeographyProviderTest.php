<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Greenland\GreenlandGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Greenland tree of 5 municipalities', function (): void {
    $areas = app(GreenlandGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'municipality'))->toHaveCount(5)
        ->and($areas->where('level', 1))->toHaveCount(5);

    // ISO 3166-2:GL codes; the National Park and Pituffik are
    // unincorporated and intentionally not listed.
    expect($byId->get('gl:municipality:avannaata')->code)->toBe('AV')
        ->and($byId->get('gl:municipality:kujalleq')->code)->toBe('KU')
        ->and($byId->get('gl:municipality:qeqertalik')->code)->toBe('QT')
        ->and($byId->get('gl:municipality:qeqqata')->code)->toBe('QE')
        ->and($byId->get('gl:municipality:sermersooq')->code)->toBe('SM')
        ->and($byId->get('gl:municipality:sermersooq')->name)->toBe('Sermersooq');
});

it('bundles the 28 municipal Greenland postcodes as single primaries', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('GL', $dir . '/greenland-postal-codes.csv', $dir . '/greenland-postal-code-areas.csv', 'aiarmada.addressing.greenland');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(28)
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue();

    $byCode = $postcodes->keyBy->code;

    // GeoNames code->town x wiki town->municipality anchors.
    expect((string) $byCode->get('3900')->areaSourceId)->toBe('gl:municipality:sermersooq')
        ->and((string) $byCode->get('3905')->areaSourceId)->toBe('gl:municipality:avannaata')
        ->and((string) $byCode->get('3910')->areaSourceId)->toBe('gl:municipality:qeqqata')
        ->and((string) $byCode->get('3913')->areaSourceId)->toBe('gl:municipality:sermersooq')
        ->and((string) $byCode->get('3924')->areaSourceId)->toBe('gl:municipality:kujalleq')
        ->and((string) $byCode->get('3930')->areaSourceId)->toBe('gl:municipality:sermersooq')
        ->and((string) $byCode->get('3952')->areaSourceId)->toBe('gl:municipality:avannaata')
        ->and((string) $byCode->get('3953')->areaSourceId)->toBe('gl:municipality:qeqertalik')
        ->and((string) $byCode->get('3964')->areaSourceId)->toBe('gl:municipality:avannaata')
        ->and((string) $byCode->get('3971')->areaSourceId)->toBe('gl:municipality:avannaata')
        ->and((string) $byCode->get('3980')->areaSourceId)->toBe('gl:municipality:sermersooq');

    // 3970 Pituffik is an unincorporated enclave surrounded by
    // Avannaata, kept on the enclosing municipality as served_by.
    expect((string) $byCode->get('3970')->areaSourceId)->toBe('gl:municipality:avannaata');

    // B1 fill: 3985 Nerlerit Inaat / Constable Pynt (Mittarfeqarfiit
    // official addressed usage; airport in Sermersooq).
    expect((string) $byCode->get('3985')->areaSourceId)->toBe('gl:municipality:sermersooq');

    // Out of the municipal system: 3972 Station Nord + 3982
    // Mestersvig + 3984 Danmarkshavn (National Park) + 2412 Santa.
    expect($byCode->has('3972'))->toBeFalse()
        ->and($byCode->has('3982'))->toBeFalse()
        ->and($byCode->has('3984'))->toBeFalse()
        ->and($byCode->has('2412'))->toBeFalse();

    $counts = $postcodes->countBy(static fn ($row): string => (string) $row->areaSourceId);

    expect($counts)->toHaveCount(5)
        ->and($counts->get('gl:municipality:sermersooq'))->toBe(8)
        ->and($counts->get('gl:municipality:avannaata'))->toBe(7)
        ->and($counts->get('gl:municipality:kujalleq'))->toBe(6)
        ->and($counts->get('gl:municipality:qeqertalik'))->toBe(4)
        ->and($counts->get('gl:municipality:qeqqata'))->toBe(3);
});
