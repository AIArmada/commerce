<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Ukraine\UkraineGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 27 first-level areas matching ISO 3166-2:UA', function (): void {
    $areas = app(UkraineGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);

    expect($l1)->toHaveCount(27)
        ->and($l1->where('type', 'oblast'))->toHaveCount(24)
        ->and($l1->where('type', 'city'))->toHaveCount(2)
        ->and($l1->where('type', 'republic'))->toHaveCount(1)
        ->and($byId->get('ua:city:kyiv')->code)->toBe('30')
        ->and($byId->get('ua:city:sevastopol')->code)->toBe('40');
});

it('ships 136 post-reform raions', function (): void {
    $areas = app(UkraineGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(136)
        ->and($l2->where('type', 'raion'))->toHaveCount(136);
});

it('links 26579 codes with the B15 re-adjudicated legs', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('UA', $dir . '/ukraine-postal-codes.csv', $dir . '/ukraine-postal-code-areas.csv', 'aiarmada.addressing.ukraine');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(26581);

    $byCode = $postcodes->groupBy->code;

    // Integrator holds: genuine cross-raion codes keep both legs (ukwiki
    // infobox postcodes: Matkiv 82563 Stryi, Pahinya + Karnachivka 47431
    // split Kremenets/Ternopil).
    expect($byCode->get('82563')->map->areaSourceId->sort()->values()->all())
        ->toBe(['ua:raion:sambir', 'ua:raion:stryi'])
        ->and($byCode->get('82563')->where('isPrimary', true)->first()->areaSourceId)->toBe('ua:raion:stryi')
        ->and($byCode->get('47431')->map->areaSourceId->sort()->values()->all())
        ->toBe(['ua:raion:kremenets', 'ua:raion:ternopil'])
        ->and($byCode->get('47431')->where('isPrimary', true)->first()->areaSourceId)->toBe('ua:raion:ternopil')
        // Integrator flip 2026-10-06: 90124 is Berehove-sole (Ukrposhta
        // operator street DB + PCIU + Siltse infobox + COD-AB; old
        // Irshavskyi split Kamianska->Berehove, so the B15 reform-wholly
        // premise was false).
        ->and($byCode->get('90124')->map->areaSourceId->all())->toBe(['ua:raion:berehove'])
        // Worker specials, integrator-verified via village postcodes.
        ->and($byCode->get('41671')->map->areaSourceId->all())->toBe(['ua:raion:konotop'])
        ->and($byCode->get('32011')->map->areaSourceId->all())->toBe(['ua:raion:khmelnytskyi'])
        ->and($byCode->get('32340')->map->areaSourceId->all())->toBe(['ua:raion:kamianets-podilskyi'])
        ->and($byCode->get('81016')->map->areaSourceId->all())->toBe(['ua:raion:yavoriv'])
        // Singles-move anchors (infobox-postcode exact sample).
        ->and($byCode->get('08411')->first()->areaSourceId)->toBe('ua:raion:boryspil')
        ->and($byCode->get('78119')->first()->areaSourceId)->toBe('ua:raion:kolomyia')
        ->and($byCode->get('16262')->first()->areaSourceId)->toBe('ua:raion:novhorod-siverskyi')
        ->and($byCode->get('28330')->first()->areaSourceId)->toBe('ua:raion:oleksandriia')
        ->and($byCode->get('08623')->first()->areaSourceId)->toBe('ua:raion:fastiv')
        ->and($byCode->get('24463')->first()->areaSourceId)->toBe('ua:raion:haisyn')
        ->and($byCode->get('28625')->first()->areaSourceId)->toBe('ua:raion:kropyvnytskyi')
        ->and($byCode->get('67633')->first()->areaSourceId)->toBe('ua:raion:odesa');
});
