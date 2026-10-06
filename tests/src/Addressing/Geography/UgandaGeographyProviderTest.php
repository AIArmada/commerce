<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Uganda\UgandaGeographyProvider;

it('pins the verified Uganda tree of 4 regions, 135 districts and 11 cities', function (): void {
    $areas = app(UgandaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'region'))->toHaveCount(4)
        ->and($areas->where('type', 'district'))->toHaveCount(135)
        ->and($areas->where('type', 'city'))->toHaveCount(11)
        ->and($areas->where('type', 'district')->where('parentSourceId', 'ug:region:central'))->toHaveCount(25)
        ->and($areas->where('type', 'district')->where('parentSourceId', 'ug:region:eastern'))->toHaveCount(37)
        ->and($areas->where('type', 'district')->where('parentSourceId', 'ug:region:northern'))->toHaveCount(38)
        ->and($areas->where('type', 'district')->where('parentSourceId', 'ug:region:western'))->toHaveCount(35)
        ->and($areas->where('type', 'city')->where('parentSourceId', 'ug:region:central'))->toHaveCount(2)
        ->and($areas->where('type', 'city')->where('parentSourceId', 'ug:region:eastern'))->toHaveCount(3)
        ->and($areas->where('type', 'city')->where('parentSourceId', 'ug:region:northern'))->toHaveCount(3)
        ->and($areas->where('type', 'city')->where('parentSourceId', 'ug:region:western'))->toHaveCount(3);

    // ISO 3166-2:UG region codes; Districts-of-Uganda oracle:
    // Madi-Okollo (ISO UG-336) and Terego (post-ISO-vintage)
    // under Northern; Fort Portal is the city-only row (its
    // district is Kabarole); Kampala capital city (ISO UG-102).
    expect($byId->get('ug:region:central')->code)->toBe('C')
        ->and($byId->get('ug:region:northern')->code)->toBe('N')
        ->and($byId->get('ug:region:western')->code)->toBe('W')
        ->and($byId->get('ug:district:madi-okollo')->parentSourceId)->toBe('ug:region:northern')
        ->and($byId->get('ug:district:terego')->parentSourceId)->toBe('ug:region:northern')
        ->and($byId->get('ug:district:bukomansimbi')->name)->toBe('Bukomansimbi')
        ->and($byId->get('ug:district:luweero')->name)->toBe('Luweero')
        ->and($byId->get('ug:city:fort-portal')->parentSourceId)->toBe('ug:region:western')
        ->and($byId->get('ug:city:kampala')->parentSourceId)->toBe('ug:region:central');
});
