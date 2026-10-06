<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Kosovo\KosovoGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 38 municipalities under 7 districts with parent links', function (): void {
    $areas = app(KosovoGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(38)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'xk:district:ferizaj'))->toHaveCount(5)
        ->and($l2->where('parentSourceId', 'xk:district:gjakova'))->toHaveCount(4)
        ->and($l2->where('parentSourceId', 'xk:district:gjilan'))->toHaveCount(6)
        ->and($l2->where('parentSourceId', 'xk:district:mitrovica'))->toHaveCount(7)
        ->and($l2->where('parentSourceId', 'xk:district:peja'))->toHaveCount(3)
        ->and($l2->where('parentSourceId', 'xk:district:pristina'))->toHaveCount(8)
        ->and($l2->where('parentSourceId', 'xk:district:prizren'))->toHaveCount(5)
        // Easily-misplaced municipalities (verified against the 7-district
        // municipality table: Rahovec sits in Gjakova district, Malisheva
        // in Prizren, Drenas and Novo Brdo in Pristina, Klina in Peja).
        ->and($byId->get('xk:municipality:rahovec')->parentSourceId)->toBe('xk:district:gjakova')
        ->and($byId->get('xk:municipality:malisheva')->parentSourceId)->toBe('xk:district:prizren')
        ->and($byId->get('xk:municipality:drenas')->parentSourceId)->toBe('xk:district:pristina')
        ->and($byId->get('xk:municipality:novo-brdo')->parentSourceId)->toBe('xk:district:pristina')
        ->and($byId->get('xk:municipality:klina')->parentSourceId)->toBe('xk:district:peja')
        // Post-2013 set: North Mitrovica ships under Mitrovica district.
        ->and($byId->get('xk:municipality:north-mitrovica')->parentSourceId)->toBe('xk:district:mitrovica');
});

it('exposes the corrected Gjakova district slug and name', function (): void {
    $areas = app(KosovoGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('xk:district:gjakova')->name)->toBe('Gjakova')
        ->and($areas->get('xk:district:gjakova')->code)->toBe('XDG')
        ->and($areas->get('xk:district:gjakova')->type)->toBe('district')
        ->and($areas->has('xk:district:gjakove'))->toBeFalse();
});

it('pins the internal district codes', function (): void {
    $byId = app(KosovoGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    // No ISO 3166-2:XK entry exists (XK is user-assigned), so these codes
    // are an internal scheme, pinned as-is. PEJ/PRI break the X-prefix
    // pattern; that inconsistency is deliberate-record, not a typo to fix.
    expect($byId->get('xk:district:ferizaj')->code)->toBe('XUF')
        ->and($byId->get('xk:district:gjakova')->code)->toBe('XDG')
        ->and($byId->get('xk:district:gjilan')->code)->toBe('XGJ')
        ->and($byId->get('xk:district:mitrovica')->code)->toBe('XKM')
        ->and($byId->get('xk:district:peja')->code)->toBe('PEJ')
        ->and($byId->get('xk:district:pristina')->code)->toBe('XPI')
        ->and($byId->get('xk:district:prizren')->code)->toBe('PRI');
});

it('uses the district-table municipality spellings', function (): void {
    $byId = app(KosovoGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($byId->get('xk:municipality:strpce')->name)->toBe('Štrpce')
        ->and($byId->get('xk:municipality:suva-reka')->name)->toBe('Suva Reka')
        ->and($byId->get('xk:municipality:kosovo-polje')->name)->toBe('Kosovo Polje')
        ->and($byId->get('xk:municipality:leposavic')->name)->toBe('Leposavić')
        ->and($byId->get('xk:municipality:zvecan')->name)->toBe('Zvečan')
        ->and($byId->get('xk:municipality:gracanica')->name)->toBe('Gračanica')
        ->and($byId->get('xk:municipality:podujeve')->name)->toBe('Podujevë')
        ->and($byId->get('xk:municipality:kacanik')->name)->toBe('Kaçanik')
        ->and($byId->get('xk:municipality:zubin-potok')->name)->toBe('Zubin Potok')
        ->and($byId->get('xk:municipality:hani-i-elezit')->name)->toBe('Hani i Elezit');
});

it('links the 127 Posta e Kosoves office codes to municipalities', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('XK', $dir . '/kosovo-postal-codes.csv', $dir . '/kosovo-postal-code-areas.csv', 'aiarmada.addressing.kosovo');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(127)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^[1-7]\d{4}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue();

    $byCode = $postcodes->keyBy->code;

    // Region capitals (PK regional PDFs + postcodebase mirrors).
    expect((string) $byCode->get('10000')->areaSourceId)->toBe('xk:municipality:pristina')
        ->and((string) $byCode->get('20000')->areaSourceId)->toBe('xk:municipality:prizren')
        ->and((string) $byCode->get('30000')->areaSourceId)->toBe('xk:municipality:peja')
        ->and((string) $byCode->get('40000')->areaSourceId)->toBe('xk:municipality:mitrovica')
        ->and((string) $byCode->get('50000')->areaSourceId)->toBe('xk:municipality:gjakova')
        ->and((string) $byCode->get('60000')->areaSourceId)->toBe('xk:municipality:gjilan')
        ->and((string) $byCode->get('70000')->areaSourceId)->toBe('xk:municipality:ferizaj')
        // Post-split remaps: the office town is now a municipality seat,
        // though the PDFs file it under the pre-split parent.
        ->and((string) $byCode->get('10500')->areaSourceId)->toBe('xk:municipality:gracanica')
        ->and((string) $byCode->get('20540')->areaSourceId)->toBe('xk:municipality:mamusha')
        ->and((string) $byCode->get('51050')->areaSourceId)->toBe('xk:municipality:junik')
        ->and((string) $byCode->get('61050')->areaSourceId)->toBe('xk:municipality:klokot')
        ->and((string) $byCode->get('71510')->areaSourceId)->toBe('xk:municipality:hani-i-elezit')
        ->and((string) $byCode->get('40650')->areaSourceId)->toBe('xk:municipality:zubin-potok')
        // 31030 Gorazhdevc sits on the 310xx Istog series but the office
        // is in Peja municipality (PDF files it under PEJE too).
        ->and((string) $byCode->get('31030')->areaSourceId)->toBe('xk:municipality:peja')
        // 60520 Zheger is official-list-only (directories are silent);
        // the village is in Gjilan municipality.
        ->and((string) $byCode->get('60520')->areaSourceId)->toBe('xk:municipality:gjilan')
        // M2 fix: 40700 Runike moved Mitrovica -> Skenderaj. The office
        // is in Runik village, Skenderaj (addressed "Runik Skenderaj
        // 40700" sighting, en/sq wiki, OSM); the PDF Mitrovica filing is
        // a postal-hierarchy artefact like 40650.
        ->and((string) $byCode->get('40700')->areaSourceId)->toBe('xk:municipality:skenderaj');
});

it('leaves the transit centre and codeless municipalities unlinked', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('XK', $dir . '/kosovo-postal-codes.csv', $dir . '/kosovo-postal-code-areas.csv', 'aiarmada.addressing.kosovo');

    $byCode = $source->postalCodes()->collect()->keyBy->code;
    $linkedAreas = $source->postalCodes()->collect()->map->areaSourceId->all();

    // 10020 QTP/TPC is the Transit Postal Centre (non-geographic), not a
    // deliverable office; Mapanet phantoms 20070/20500 are not PK codes.
    expect($byCode->has('10020'))->toBeFalse()
        ->and($byCode->has('20070'))->toBeFalse()
        ->and($byCode->has('20500'))->toBeFalse()
        // Parteš (Serbian 38251) and Ranilug (Serbian 38267) have Pošta
        // Srbije offices, not Posta e Kosovës offices; North Mitrovica
        // has no PK office (Serbian 38220 / PK-side 40000 reuse).
        ->and($linkedAreas)->not->toContain('xk:municipality:partes')
        ->and($linkedAreas)->not->toContain('xk:municipality:ranilug')
        ->and($linkedAreas)->not->toContain('xk:municipality:north-mitrovica');
});
