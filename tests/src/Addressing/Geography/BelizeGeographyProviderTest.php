<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Belize\BelizeGeographyProvider;

it('pins the verified Belize tree of 6 districts', function (): void {
    $areas = app(BelizeGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'district'))->toHaveCount(6)
        ->and($areas->where('level', 1))->toHaveCount(6);

    // ISO 3166-2:BZ codes, exact set.
    expect($byId->get('bz:district:belize')->code)->toBe('BZ')
        ->and($byId->get('bz:district:cayo')->code)->toBe('CY')
        ->and($byId->get('bz:district:corozal')->code)->toBe('CZL')
        ->and($byId->get('bz:district:orange-walk')->code)->toBe('OW')
        ->and($byId->get('bz:district:stann-creek')->code)->toBe('SC')
        ->and($byId->get('bz:district:toledo')->code)->toBe('TOL');
});
