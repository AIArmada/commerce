<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Cameroon\CameroonGeographyProvider;

it('pins the verified Cameroon tree of 10 regions and 58 departments', function (): void {
    $areas = app(CameroonGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'region'))->toHaveCount(10)
        ->and($areas->where('type', 'department'))->toHaveCount(58)
        ->and($areas->where('parentSourceId', 'cm:region:centre'))->toHaveCount(10)
        ->and($areas->where('parentSourceId', 'cm:region:west'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'cm:region:northwest'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'cm:region:far-north'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'cm:region:southwest'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'cm:region:adamawa'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'cm:region:east'))->toHaveCount(4);

    // ISO 3166-2:CM codes; Departments-of-Cameroon oracle: Mfoundi
    // (Yaoundé) under Centre, Wouri (Douala) under Littoral.
    expect($byId->get('cm:region:adamawa')->code)->toBe('AD')
        ->and($byId->get('cm:region:west')->code)->toBe('OU')
        ->and($byId->get('cm:region:far-north')->code)->toBe('EN')
        ->and($byId->get('cm:department:mfoundi')->parentSourceId)->toBe('cm:region:centre')
        ->and($byId->get('cm:department:wouri')->parentSourceId)->toBe('cm:region:littoral');
});
