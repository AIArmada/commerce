<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Dominica\DominicaGeographyProvider;

it('pins the verified Dominica tree of 10 parishes', function (): void {
    $areas = app(DominicaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'parish'))->toHaveCount(10)
        ->and($areas->where('level', 1))->toHaveCount(10);

    // ISO 3166-2:DM codes 02-11 (01 unassigned). No postcode
    // system (UPU dmaEn is example + contact only).
    expect($byId->get('dm:parish:saint-andrew')->code)->toBe('02')
        ->and($byId->get('dm:parish:saint-david')->code)->toBe('03')
        ->and($byId->get('dm:parish:saint-george')->code)->toBe('04')
        ->and($byId->get('dm:parish:saint-john')->code)->toBe('05')
        ->and($byId->get('dm:parish:saint-joseph')->code)->toBe('06')
        ->and($byId->get('dm:parish:saint-luke')->code)->toBe('07')
        ->and($byId->get('dm:parish:saint-mark')->code)->toBe('08')
        ->and($byId->get('dm:parish:saint-patrick')->code)->toBe('09')
        ->and($byId->get('dm:parish:saint-paul')->code)->toBe('10')
        ->and($byId->get('dm:parish:saint-peter')->code)->toBe('11');
});
