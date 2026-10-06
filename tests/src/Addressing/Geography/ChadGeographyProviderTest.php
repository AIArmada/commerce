<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Chad\ChadGeographyProvider;

it('pins the verified Chad tree of 23 provinces and 63 departments', function (): void {
    $areas = app(ChadGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'province'))->toHaveCount(23)
        ->and($areas->where('type', 'department'))->toHaveCount(63)
        ->and($areas->where('parentSourceId', 'td:province:logone-oriental'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'td:province:guera'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'td:province:logone-occidental'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'td:province:mayo-kebbi-est'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'td:province:batha'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'td:province:lac'))->toHaveCount(2)
        ->and($areas->where('parentSourceId', 'td:province:ndjamena'))->toHaveCount(0);

    // ISO 3166-2:TD codes (2018 23-province set); pre-2024
    // Departments-of-Chad oracle: Mourtcha under Ennedi-Ouest,
    // Lac Wey under Logone Occidental, Borkou Yala under Borkou,
    // N'Djamena terminal.
    expect($byId->get('td:province:ndjamena')->code)->toBe('ND')
        ->and($byId->get('td:province:ennedi-est')->code)->toBe('EE')
        ->and($byId->get('td:province:ennedi-ouest')->code)->toBe('EO')
        ->and($byId->get('td:province:wadi-fira')->code)->toBe('WF')
        ->and($byId->get('td:department:mourtcha')->parentSourceId)->toBe('td:province:ennedi-ouest')
        ->and($byId->get('td:department:lac-wey')->parentSourceId)->toBe('td:province:logone-occidental')
        ->and($byId->get('td:department:borkou-yala')->parentSourceId)->toBe('td:province:borkou');
});
