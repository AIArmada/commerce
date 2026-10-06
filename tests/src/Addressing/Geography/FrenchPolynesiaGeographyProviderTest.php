<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\FrenchPolynesia\FrenchPolynesiaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified French Polynesia tree of 5 divisions and 48 communes', function (): void {
    $areas = app(FrenchPolynesiaGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas->where('type', 'division'))->toHaveCount(5)
        ->and($areas->where('type', 'commune'))->toHaveCount(48)
        ->and($areas->where('level', 1))->toHaveCount(5)
        ->and($areas->where('level', 2))->toHaveCount(48);

    $byId = $areas->keyBy->sourceId;

    // INSEE commune codes 98711-98758 (ISPF RP2022 roster + WP admin-divisions table).
    expect($byId->get('pf:commune:anaa')->code)->toBe('98711')
        ->and($byId->get('pf:commune:papeete')->code)->toBe('98735')
        ->and($byId->get('pf:commune:puka-puka')->code)->toBe('98737')
        ->and($byId->get('pf:commune:uturoa')->code)->toBe('98758')
        ->and($byId->get('pf:commune:tahuata')->code)->toBe('98746')
        ->and($byId->get('pf:commune:gambier')->code)->toBe('98719');

    // Tahitian-diacritic display names follow WP; INSEE COG/ISPF print ASCII
    // (Faaa, Pirae, Pukapuka, Punaauia). Kept on tie, pinned here.
    expect($byId->get('pf:commune:faaa')->name)->toBe('Faʻaʻā')
        ->and($byId->get('pf:commune:pirae')->name)->toBe('Pīraʻe')
        ->and($byId->get('pf:commune:punaauia')->name)->toBe('Punaʻauia');

    // Parent map: Marquesas 6, Tuamotu-Gambier 17, Austral 5, Leeward 7, Windward 13.
    $kids = static fn (string $parent): array => $areas->where('parentSourceId', $parent)
        ->map(static fn ($a): string => $a->sourceId)->sort()->values()->all();

    expect($kids('pf:division:marquesas-islands'))->toBe([
        'pf:commune:fatu-hiva', 'pf:commune:hiva-oa', 'pf:commune:nuku-hiva',
        'pf:commune:tahuata', 'pf:commune:ua-huka', 'pf:commune:ua-pou',
    ])->and($kids('pf:division:austral-islands'))->toBe([
        'pf:commune:raivavae', 'pf:commune:rapa', 'pf:commune:rimatara',
        'pf:commune:rurutu', 'pf:commune:tubuai',
    ])->and($kids('pf:division:leeward-islands'))->toBe([
        'pf:commune:bora-bora', 'pf:commune:huahine', 'pf:commune:maupiti',
        'pf:commune:tahaa', 'pf:commune:taputapuatea', 'pf:commune:tumaraa',
        'pf:commune:uturoa',
    ]);

    expect($areas->where('parentSourceId', 'pf:division:tuamotu-gambier'))->toHaveCount(17)
        ->and($areas->where('parentSourceId', 'pf:division:windward-islands'))->toHaveCount(13);

    // No orphans: every parent resolves.
    $ids = $areas->map(static fn ($a): string => $a->sourceId)->all();

    expect($areas->pluck('parentSourceId')->filter()->diff($ids)->all())->toBe([]);
});

it('bundles the 83-code 93-leg PF overlay with all 10 shared-code legs', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('PF', $dir . '/french-polynesia-postal-codes.csv', $dir . '/french-polynesia-postal-code-areas.csv', 'aiarmada.addressing.french_polynesia');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(93)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^987\d\d$/', (string) $row->code)))->toBeTrue();

    // Exactly one primary per code over 83 distinct codes.
    expect($postcodes->where('isPrimary', true))->toHaveCount(83)
        ->and($postcodes->map(static fn ($row): string => (string) $row->code)->unique()->count())->toBe(83)
        ->and($postcodes->where('isPrimary', false))->toHaveCount(10);

    $primaries = $postcodes->where('isPrimary', true)->keyBy->code;

    // Papeete/Faaa/Punaauia cluster + UPU PYF anchor 98709 Mahina.
    expect((string) $primaries->get('98714')->areaSourceId)->toBe('pf:commune:papeete')
        ->and((string) $primaries->get('98704')->areaSourceId)->toBe('pf:commune:faaa')
        ->and((string) $primaries->get('98703')->areaSourceId)->toBe('pf:commune:punaauia')
        ->and((string) $primaries->get('98718')->areaSourceId)->toBe('pf:commune:punaauia')
        ->and((string) $primaries->get('98709')->areaSourceId)->toBe('pf:commune:mahina');

    // Shared-code primaries (oracles list associations, not primaries; ties keep).
    expect((string) $primaries->get('98732')->areaSourceId)->toBe('pf:commune:huahine')
        ->and((string) $primaries->get('98735')->areaSourceId)->toBe('pf:commune:uturoa')
        ->and((string) $primaries->get('98790')->areaSourceId)->toBe('pf:commune:rangiroa')
        ->and((string) $primaries->get('98796')->areaSourceId)->toBe('pf:commune:nuku-hiva');

    // All 10 secondary legs (GeoNames + MFG + Etalab, 3 signals each).
    $legs = $postcodes->map(static fn ($row): string => (string) $row->code . ':' . (string) $row->areaSourceId)->all();

    foreach (['98732:pf:commune:maupiti', '98735:pf:commune:taputapuatea', '98735:pf:commune:tumaraa',
        '98790:pf:commune:anaa', '98790:pf:commune:fakarava', '98790:pf:commune:hao',
        '98790:pf:commune:hikueru', '98790:pf:commune:makemo', '98790:pf:commune:takaroa',
        '98796:pf:commune:hiva-oa'] as $leg) {
        expect($legs)->toContain($leg);
    }

    // Per-commune link counts for the multi-code communes.
    $counts = $postcodes->countBy(static fn ($row): string => (string) $row->areaSourceId);

    expect($counts->get('pf:commune:rangiroa'))->toBe(5)
        ->and($counts->get('pf:commune:fakarava'))->toBe(4)
        ->and($counts->get('pf:commune:hitiaa-o-te-ra'))->toBe(4)
        ->and($counts->get('pf:commune:taiarapu-est'))->toBe(4)
        ->and($counts->get('pf:commune:gambier'))->toBe(3)
        ->and($counts->get('pf:commune:hiva-oa'))->toBe(3)
        ->and($counts->get('pf:commune:nuku-hiva'))->toBe(3)
        ->and($counts->get('pf:commune:taiarapu-ouest'))->toBe(3);
});
