<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Algeria\AlgeriaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Algeria tree of 69 wilayas and 548 dairas', function (): void {
    $areas = app(AlgeriaGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas->where('type', 'wilaya'))->toHaveCount(69)
        ->and($areas->where('type', 'daira'))->toHaveCount(548)
        ->and($areas->where('level', 1))->toHaveCount(69)
        ->and($areas->where('parentSourceId', 'dz:wilaya:tizi-ouzou'))->toHaveCount(21)
        ->and($areas->where('parentSourceId', 'dz:wilaya:setif'))->toHaveCount(20)
        ->and($areas->where('parentSourceId', 'dz:wilaya:bejaia'))->toHaveCount(19)
        ->and($areas->where('parentSourceId', 'dz:wilaya:tlemcen'))->toHaveCount(19)
        ->and($areas->where('parentSourceId', 'dz:wilaya:batna'))->toHaveCount(18)
        ->and($areas->where('parentSourceId', 'dz:wilaya:alger'))->toHaveCount(13)
        ->and($areas->where('parentSourceId', 'dz:wilaya:bou-saada'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'dz:wilaya:aflou'))->toHaveCount(5);

    $byId = $areas->keyBy->sourceId;

    // ISO 3166-2:DZ codes (11 Tamanghasset for Tamanrasset, 42 Tipasa
    // for Tipaza, 57 El M'ghair for El Meghaier, 58 El Menia for El
    // Meniaa are deliberate display deviations).
    expect($byId->get('dz:wilaya:alger')->code)->toBe('16')
        ->and($byId->get('dz:wilaya:tamanghasset')->code)->toBe('11')
        ->and($byId->get('dz:wilaya:tipasa')->code)->toBe('42')
        ->and($byId->get('dz:wilaya:touggourt')->code)->toBe('55')
        ->and($byId->get('dz:wilaya:el-menia')->code)->toBe('58')
        ->and($byId->get('dz:wilaya:aflou')->code)->toBe('59')
        ->and($byId->get('dz:wilaya:bou-saada')->code)->toBe('68')
        ->and($byId->get('dz:wilaya:el-abiodh-sidi-cheikh')->code)->toBe('69')
        ->and($byId->get('dz:daira:aflou:oued-morra')->parentSourceId)->toBe('dz:wilaya:aflou')
        ->and($byId->get('dz:daira:el-aricha:el-aricha')->parentSourceId)->toBe('dz:wilaya:el-aricha')
        ->and($byId->get('dz:daira:ouargla:el-borma')->parentSourceId)->toBe('dz:wilaya:ouargla');
});

it('bundles the 3908 daira postcodes as single primary links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('DZ', $dir . '/algeria-postal-codes.csv', $dir . '/algeria-postal-code-areas.csv', 'aiarmada.addressing.algeria');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(3908)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{5}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue()
        ->and($postcodes->pluck('code')->unique())->toHaveCount(3908);

    $primaries = $postcodes->where('isPrimary', true)->keyBy->code;

    expect($primaries)->toHaveCount(3908);

    // UPU dzaEn anchor: 16027 ALGIERS (Baraki office).
    expect((string) $primaries->get('16027')->areaSourceId)->toBe('dz:daira:alger:baraki')
        ->and((string) $primaries->get('01000')->areaSourceId)->toBe('dz:daira:adrar:adrar')
        ->and((string) $primaries->get('31000')->areaSourceId)->toBe('dz:daira:oran:oran')
        ->and((string) $primaries->get('30025')->areaSourceId)->toBe('dz:daira:ouargla:el-borma');

    // M7 retargets: Aflou-wilaya offices to their JO dairas.
    expect((string) $primaries->get('03027')->areaSourceId)->toBe('dz:daira:aflou:oued-morra')
        ->and((string) $primaries->get('03041')->areaSourceId)->toBe('dz:daira:aflou:oued-morra')
        ->and((string) $primaries->get('03034')->areaSourceId)->toBe('dz:daira:aflou:aflou')
        ->and((string) $primaries->get('03013')->areaSourceId)->toBe('dz:daira:aflou:gueltat-sidi-saad');

    // M7 retargets: Djelfa/Tiaret/Batna/Tebessa/Medea offices.
    expect((string) $primaries->get('17043')->areaSourceId)->toBe('dz:daira:ain-ouessara:birine')
        ->and((string) $primaries->get('17051')->areaSourceId)->toBe('dz:daira:ain-ouessara:sidi-ladjel')
        ->and((string) $primaries->get('17021')->areaSourceId)->toBe('dz:daira:messaad:faidh-el-botma')
        ->and((string) $primaries->get('14018')->areaSourceId)->toBe('dz:daira:ksar-chellala:hamadia')
        ->and((string) $primaries->get('05046')->areaSourceId)->toBe('dz:daira:barika:djezzar')
        ->and((string) $primaries->get('12044')->areaSourceId)->toBe('dz:daira:bir-el-ater:negrine')
        ->and((string) $primaries->get('12043')->areaSourceId)->toBe('dz:daira:bir-el-ater:bir-el-ater')
        ->and((string) $primaries->get('26058')->areaSourceId)->toBe('dz:daira:medea:tablat');

    // Deliberate keeps: BOD/Debdeb/Ain Smara are communes, not dairas.
    expect((string) $primaries->get('33003')->areaSourceId)->toBe('dz:daira:illizi:in-amenas')
        ->and((string) $primaries->get('33004')->areaSourceId)->toBe('dz:daira:illizi:in-amenas')
        ->and((string) $primaries->get('25006')->areaSourceId)->toBe('dz:daira:constantine:el-khroub');

    // Per-wilaya primary counts (new wilayas carry mother-prefix codes).
    $areas = app(AlgeriaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;
    $counts = $primaries->countBy(static fn ($row) => (string) $areas->get((string) $row->areaSourceId)->parentSourceId);

    expect($counts->get('dz:wilaya:alger'))->toBe(214)
        ->and($counts->get('dz:wilaya:tizi-ouzou'))->toBe(161)
        ->and($counts->get('dz:wilaya:setif'))->toBe(145)
        ->and($counts->get('dz:wilaya:batna'))->toBe(129)
        ->and($counts->get('dz:wilaya:oran'))->toBe(119)
        ->and($counts->get('dz:wilaya:bou-saada'))->toBe(42)
        ->and($counts->get('dz:wilaya:touggourt'))->toBe(33)
        ->and($counts->get('dz:wilaya:aflou'))->toBe(23);
});
