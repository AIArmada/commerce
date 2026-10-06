<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\HongKong\HongKongGeographyProvider;

it('pins the verified Hong Kong tree of 18 districts', function (): void {
    $areas = app(HongKongGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'district'))->toHaveCount(18)
        ->and($areas->where('level', 1))->toHaveCount(18);

    // No ISO 3166-2:HK subdivisions; H/K/N-prefix codes synthetic
    // (HKI 4, Kowloon 5, NT 9). Names 18/18 vs Districts of Hong
    // Kong + Statoids. No postcode system (UPU hkg), so no postal
    // files ship; 999077 is mainland-CN-assigned and excluded.
    expect($byId->get('hk:district:central-and-western')->code)->toBe('HCW')
        ->and($byId->get('hk:district:eastern')->code)->toBe('HEA')
        ->and($byId->get('hk:district:southern')->code)->toBe('HSO')
        ->and($byId->get('hk:district:wan-chai')->code)->toBe('HWC')
        ->and($byId->get('hk:district:kowloon-city')->code)->toBe('KKC')
        ->and($byId->get('hk:district:yau-tsim-mong')->code)->toBe('KYT')
        ->and($byId->get('hk:district:islands')->code)->toBe('NIS')
        ->and($byId->get('hk:district:north')->code)->toBe('NNO')
        ->and($byId->get('hk:district:yuen-long')->code)->toBe('NYL');
});
