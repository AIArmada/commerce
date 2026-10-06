<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Nicaragua\NicaraguaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 17 first-level areas with ISO 3166-2:NI codes', function (): void {
    $areas = app(NicaraguaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);

    expect($l1)->toHaveCount(17)
        ->and($l1->where('type', 'department'))->toHaveCount(15)
        ->and($l1->where('type', 'autonomous_region'))->toHaveCount(2)
        ->and($l1->map->code->sort()->values()->all())->toBe(['AN', 'AS', 'BO', 'CA', 'CI', 'CO', 'ES', 'GR', 'JI', 'LE', 'MD', 'MN', 'MS', 'MT', 'NS', 'RI', 'SJ'])
        ->and($byId->get('ni:department:managua')->code)->toBe('MN')
        ->and($byId->get('ni:autonomous_region:costa-caribe-norte')->code)->toBe('AN');
});

it('ships 153 municipalities with the B15 renames and keeps', function (): void {
    $areas = app(NicaraguaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(153)
        // B15 renames (INIDE gazetteer + operator maps + enwiki).
        ->and($byId->get('ni:municipality:san-juan-del-rio-coco')->name)->toBe('San Juan del Río Coco')
        ->and($byId->has('ni:municipality:san-juan-de-rio-coco'))->toBeFalse()
        ->and($byId->get('ni:municipality:waspan')->name)->toBe('Waspam')
        // 2026-10-06: Ley 434 + consolidated Ley 59 + INIDE anuario2023 +
        // operator print-all x4 + enwiki; id keeps the pre-2002 slug as stable key.
        ->and($byId->get('ni:municipality:san-juan-del-norte')->name)->toBe('San Juan de Nicaragua')
        // B15 keeps (operator maps + eswiki + citypopulation).
        ->and($byId->get('ni:municipality:el-jicaro')->name)->toBe('El Jícaro')
        ->and($byId->get('ni:municipality:kukra-hill')->name)->toBe('Kukra Hill')
        ->and($byId->get('ni:municipality:mulukuku')->name)->toBe('Mulukukú');
});

it('links 890 operator-verified codes with zero multis', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('NI', $dir . '/nicaragua-postal-codes.csv', $dir . '/nicaragua-postal-code-areas.csv', 'aiarmada.addressing.nicaragua');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(890);

    $byCode = $postcodes->groupBy->code;

    // Department bases (archived Correos dept maps + Nominatim).
    expect($byCode->get('10000')->first()->areaSourceId)->toBe('ni:municipality:managua')
        ->and($byCode->get('21000')->first()->areaSourceId)->toBe('ni:municipality:leon')
        ->and($byCode->get('71000')->first()->areaSourceId)->toBe('ni:municipality:puerto-cabezas')
        ->and($byCode->get('91000')->first()->areaSourceId)->toBe('ni:municipality:san-carlos')
        // Flagged codes resolved for the bundle (codigo-postal.org +
        // operator maps overruled OSM misattributions).
        ->and($byCode->get('42600')->first()->areaSourceId)->toBe('ni:municipality:masatepe')
        ->and($byCode->get('46400')->first()->areaSourceId)->toBe('ni:municipality:san-marcos')
        ->and($byCode->get('46600')->first()->areaSourceId)->toBe('ni:municipality:santa-teresa')
        ->and($byCode->get('48500')->first()->areaSourceId)->toBe('ni:municipality:tola')
        ->and($byCode->get('66600')->first()->areaSourceId)->toBe('ni:municipality:san-jose-de-bocay')
        ->and($byCode->get('66700')->first()->areaSourceId)->toBe('ni:municipality:wiwili-de-jinotega')
        // Rename legs retargeted.
        ->and($byCode->get('35800')->first()->areaSourceId)->toBe('ni:municipality:san-juan-del-rio-coco')
        ->and($byCode->get('72100')->first()->areaSourceId)->toBe('ni:municipality:waspan')
        // Managua sector anchors (Mapanet + codigo-postal.org + OSM).
        ->and($byCode->get('11001')->first()->areaSourceId)->toBe('ni:municipality:managua')
        ->and($byCode->get('12065')->first()->areaSourceId)->toBe('ni:municipality:managua')
        ->and($byCode->get('14319')->first()->areaSourceId)->toBe('ni:municipality:managua')
        // Triple-absent holes stay absent.
        ->and($byCode->has('13003'))->toBeFalse()
        ->and($byCode->has('11000'))->toBeFalse()
        ->and($byCode->has('27000'))->toBeFalse();
});
