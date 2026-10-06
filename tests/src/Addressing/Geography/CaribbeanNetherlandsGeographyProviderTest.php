<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\CaribbeanNetherlands\CaribbeanNetherlandsGeographyProvider;

it('pins the verified Caribbean Netherlands tree of 3 special municipalities', function (): void {
    $areas = app(CaribbeanNetherlandsGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'special_municipality'))->toHaveCount(3)
        ->and($areas->where('level', 1))->toHaveCount(3);

    // ISO 3166-2:BQ codes. No postcode system (UPU besEn carries
    // operator info only); postcodes planned by end 2026.
    expect($byId->get('bq:special_municipality:bonaire')->code)->toBe('BO')
        ->and($byId->get('bq:special_municipality:saba')->code)->toBe('SA')
        ->and($byId->get('bq:special_municipality:sint-eustatius')->code)->toBe('SE')
        ->and($byId->get('bq:special_municipality:sint-eustatius')->name)->toBe('Sint Eustatius');
});
