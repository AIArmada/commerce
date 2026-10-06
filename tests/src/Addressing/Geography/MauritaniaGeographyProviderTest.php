<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Mauritania\MauritaniaGeographyProvider;

it('pins the verified Mauritania tree of 15 regions and 63 departments', function (): void {
    $areas = app(MauritaniaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'region'))->toHaveCount(15)
        ->and($areas->where('type', 'department'))->toHaveCount(63)
        ->and($areas->where('parentSourceId', 'mr:region:hodh-ech-chargui'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'mr:region:trarza'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'mr:region:brakna'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'mr:region:assaba'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'mr:region:hodh-el-gharbi'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'mr:region:gorgol'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'mr:region:adrar'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'mr:region:guidimaka'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'mr:region:nouakchott-nord'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'mr:region:nouakchott-ouest'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'mr:region:nouakchott-sud'))->toHaveCount(3);

    // ISO 3166-2:MR codes; 2014 Nouakchott 3-way split per the
    // Departments-of-Mauritania oracle: Ksar under Ouest, Dar Naim
    // under Nord, Arafat under Sud.
    expect($byId->get('mr:region:hodh-ech-chargui')->code)->toBe('01')
        ->and($byId->get('mr:region:dakhlet-nouadhibou')->code)->toBe('08')
        ->and($byId->get('mr:region:nouakchott-ouest')->code)->toBe('13')
        ->and($byId->get('mr:region:nouakchott-nord')->code)->toBe('14')
        ->and($byId->get('mr:region:nouakchott-sud')->code)->toBe('15')
        ->and($byId->get('mr:department:ksar')->parentSourceId)->toBe('mr:region:nouakchott-ouest')
        ->and($byId->get('mr:department:dar-naim')->parentSourceId)->toBe('mr:region:nouakchott-nord')
        ->and($byId->get('mr:department:arafat')->parentSourceId)->toBe('mr:region:nouakchott-sud');
});
