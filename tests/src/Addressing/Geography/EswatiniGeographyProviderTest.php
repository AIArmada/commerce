<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Eswatini\EswatiniGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Eswatini tree of 4 regions and 59 tinkhundla', function (): void {
    $areas = app(EswatiniGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'region'))->toHaveCount(4)
        ->and($areas->where('type', 'inkhundla'))->toHaveCount(59)
        ->and($areas->where('level', 1))->toHaveCount(4)
        ->and($areas->where('level', 2))->toHaveCount(59);

    // ISO 3166-2:SZ anchors: the four regions carry HH/LU/MA/SH.
    expect($byId->get('sz:region:hhohho')->code)->toBe('HH')
        ->and($byId->get('sz:region:lubombo')->code)->toBe('LU')
        ->and($byId->get('sz:region:manzini')->code)->toBe('MA')
        ->and($byId->get('sz:region:shiselweni')->code)->toBe('SH');

    // EBC 2018 per-region counts: 15/11/18/15.
    $kids = static fn (string $parent): int => $areas
        ->where('parentSourceId', $parent)->count();

    expect($kids('sz:region:hhohho'))->toBe(15)
        ->and($kids('sz:region:lubombo'))->toBe(11)
        ->and($kids('sz:region:manzini'))->toBe(18)
        ->and($kids('sz:region:shiselweni'))->toBe(15);
});

it('pins the four 2018 inkhundla added since the stale 55-tree', function (): void {
    $areas = app(EswatiniGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    // EBC 2018 results + turnout roster, each corroborated externally:
    // Siphocosini (UNDP Hhohho blog, Times), Nkomiyahlaba + Phondo
    // (gov.sz service charter, Observer), Kumethula (gov.sz charter,
    // Observer, ESCC).
    expect($byId->get('sz:inkhundla:siphocosini')->name)->toBe('Siphocosini')
        ->and($byId->get('sz:inkhundla:siphocosini')->parentSourceId)->toBe('sz:region:hhohho')
        ->and($byId->get('sz:inkhundla:nkomiyahlaba')->name)->toBe('Nkomiyahlaba')
        ->and($byId->get('sz:inkhundla:nkomiyahlaba')->parentSourceId)->toBe('sz:region:manzini')
        ->and($byId->get('sz:inkhundla:phondo')->name)->toBe('Phondo')
        ->and($byId->get('sz:inkhundla:phondo')->parentSourceId)->toBe('sz:region:manzini')
        ->and($byId->get('sz:inkhundla:kumethula')->name)->toBe('Kumethula')
        ->and($byId->get('sz:inkhundla:kumethula')->parentSourceId)->toBe('sz:region:shiselweni');
});

it('pins the Gilgal and Zombodze Emuva entity renames with new ids', function (): void {
    $areas = app(EswatiniGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    // Genuine 2018 renames, not spelling fixes, so the source ids turn
    // over: Hlane->Gilgal (EBC + SADC ECF 2023 + UN + Observer),
    // Zombodze->Zombodze Emuva (EBC 2018 + 2023 posters/winners +
    // bird-agency 2023 constituency report; Observer shortens to
    // Zombodze, recorded as a variant).
    expect($byId->get('sz:inkhundla:gilgal')->name)->toBe('Gilgal')
        ->and($byId->get('sz:inkhundla:gilgal')->parentSourceId)->toBe('sz:region:lubombo')
        ->and($byId->get('sz:inkhundla:zombodze-emuva')->name)->toBe('Zombodze Emuva')
        ->and($byId->get('sz:inkhundla:zombodze-emuva')->parentSourceId)->toBe('sz:region:shiselweni')
        ->and($byId->has('sz:inkhundla:hlane'))->toBeFalse()
        ->and($byId->has('sz:inkhundla:zombodze'))->toBeFalse();
});

it('pins the five spelling renames with stable ids and the holds', function (): void {
    $areas = app(EswatiniGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    // Spelling fixes keep their ids: EBC + Observer agree on each new
    // spelling (Mhlambanyatsi also Statoids-primary + Agriculture +
    // UNDP; Mpolonjeni also new-Statoids; Timphisini also gov.sz
    // charter + Times; Lubulini also World Vision).
    expect($byId->get('sz:inkhundla:hlambanyatsi')->name)->toBe('Mhlambanyatsi')
        ->and($byId->get('sz:inkhundla:ekukhanyeni')->name)->toBe('Kukhanyeni')
        ->and($byId->get('sz:inkhundla:mpholonjeni')->name)->toBe('Mpolonjeni')
        ->and($byId->get('sz:inkhundla:lubuli')->name)->toBe('Lubulini')
        ->and($byId->get('sz:inkhundla:timpisini')->name)->toBe('Timphisini');

    // Holds: EBC contradicts itself (Madlamphisi/Madlangempisi,
    // Mahlangatja/Mahlangatsha, Pigg's/Piggs) so bundled stays; 1-1
    // EBC-vs-independents ties (Motjane: Observer + Statoids agree
    // bundled; Shiselweni I/II: Observer agrees bundled) stay; EBC
    // agrees bundled on Maphalaleni, Mtfongwaneni, Ngwempisi,
    // Lamgabhi; Nkilongo/Ntondozi/Mangcongco unanimous.
    expect($byId->get('sz:inkhundla:motjane')->name)->toBe('Motjane')
        ->and($byId->get('sz:inkhundla:madlangempisi')->name)->toBe('Madlangempisi')
        ->and($byId->get('sz:inkhundla:mahlangatja')->name)->toBe('Mahlangatja')
        ->and($byId->get('sz:inkhundla:piggs-peak')->name)->toBe('Piggs Peak')
        ->and($byId->get('sz:inkhundla:shiselweni-i')->name)->toBe('Shiselweni I')
        ->and($byId->get('sz:inkhundla:shiselweni-ii')->name)->toBe('Shiselweni II')
        ->and($byId->get('sz:inkhundla:maphalaleni')->name)->toBe('Maphalaleni')
        ->and($byId->get('sz:inkhundla:mtfongwaneni')->name)->toBe('Mtfongwaneni')
        ->and($byId->get('sz:inkhundla:ngwempisi')->name)->toBe('Ngwempisi')
        ->and($byId->get('sz:inkhundla:lamgabhi')->name)->toBe('Lamgabhi');
});

it('bundles the 81 SZ postcodes with H101 filled at region primaries', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('SZ', $dir . '/eswatini-postal-codes.csv', $dir . '/eswatini-postal-code-areas.csv', 'aiarmada.addressing.eswatini');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(81)
        ->and($postcodes->filter(static fn ($row): bool => $row->isPrimary))->toHaveCount(81);

    $byCode = $postcodes->groupBy->code;

    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // H101 Swazi Plaza fill (WP postcodes + youbianku agree) plus the
    // four regional capitals (Mbabane H100 is the UPU anchor).
    expect($primary('H101'))->toBe('sz:region:hhohho')
        ->and($primary('H100'))->toBe('sz:region:hhohho')
        ->and($primary('M200'))->toBe('sz:region:manzini')
        ->and($primary('S400'))->toBe('sz:region:shiselweni')
        ->and($primary('L300'))->toBe('sz:region:lubombo');

    // Youbianku-only extras kept: Emsahweni/Mahlanya/Ebuhleni/The
    // Gables/The Hub.
    expect($primary('H121'))->toBe('sz:region:hhohho')
        ->and($primary('H124'))->toBe('sz:region:hhohho')
        ->and($primary('H125'))->toBe('sz:region:hhohho')
        ->and($primary('H126'))->toBe('sz:region:hhohho')
        ->and($primary('M223'))->toBe('sz:region:manzini');

    // M211 Sithobela + M214 Siphofaneni keep M-region links: links
    // follow the code letter at region level even though the
    // tinkhundla sit in Lubombo (postal/admin boundary mismatch).
    expect($primary('M211'))->toBe('sz:region:manzini')
        ->and($primary('M214'))->toBe('sz:region:manzini');
});
