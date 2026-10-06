<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Gabon\GabonGeographyProvider;

it('pins the verified Gabon tree of 9 provinces and 49 departments', function (): void {
    $areas = app(GabonGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'province'))->toHaveCount(9)
        ->and($areas->where('type', 'department'))->toHaveCount(49)
        ->and($areas->where('parentSourceId', 'ga:province:estuaire'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'ga:province:haut-ogooue'))->toHaveCount(11)
        ->and($areas->where('parentSourceId', 'ga:province:moyen-ogooue'))->toHaveCount(2)
        ->and($areas->where('parentSourceId', 'ga:province:ngounie'))->toHaveCount(9)
        ->and($areas->where('parentSourceId', 'ga:province:nyanga'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'ga:province:ogooue-ivindo'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'ga:province:ogooue-lolo'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'ga:province:ogooue-maritime'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'ga:province:woleu-ntem'))->toHaveCount(5);

    // ISO 3166-2:GA digits; Departments-of-Gabon oracle: Cap Estérias
    // (deleted 2013) correctly absent, Leboumbi-Leyou corrected spelling.
    expect($byId->get('ga:province:estuaire')->code)->toBe('1')
        ->and($byId->get('ga:province:woleu-ntem')->code)->toBe('9')
        ->and($byId->get('ga:province:ogooue-maritime')->name)->toBe('Ogooué-Maritime')
        ->and($areas->where('name', 'Cap Estérias'))->toHaveCount(0);
});
