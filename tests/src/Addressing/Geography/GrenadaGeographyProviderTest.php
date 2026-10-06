<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Grenada\GrenadaGeographyProvider;

it('pins the verified Grenada tree of 6 parishes and Carriacou', function (): void {
    $areas = app(GrenadaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'parish'))->toHaveCount(6)
        ->and($areas->where('type', 'dependency'))->toHaveCount(1);

    // ISO 3166-2:GD codes; GD-10 Southern Grenadine Islands ships
    // under the common dependency name Carriacou. No postcode
    // system (UPU grdEn carries no postcode section).
    expect($byId->get('gd:parish:saint-andrew')->code)->toBe('01')
        ->and($byId->get('gd:parish:saint-david')->code)->toBe('02')
        ->and($byId->get('gd:parish:saint-george')->code)->toBe('03')
        ->and($byId->get('gd:parish:saint-john')->code)->toBe('04')
        ->and($byId->get('gd:parish:saint-mark')->code)->toBe('05')
        ->and($byId->get('gd:parish:saint-patrick')->code)->toBe('06')
        ->and($byId->get('gd:dependency:carriacou')->code)->toBe('10');
});
