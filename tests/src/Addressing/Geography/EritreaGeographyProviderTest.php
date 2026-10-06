<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Eritrea\EritreaGeographyProvider;

it('ships 6 ISO-coded regions with 58 subregions', function (): void {
    $areas = app(EritreaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'subregion');

    expect($areas->where('type', 'region'))->toHaveCount(6)
        ->and($l2)->toHaveCount(58)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('er:region:anseba')->code)->toBe('AN')
        ->and($byId->get('er:region:debub')->code)->toBe('DU')
        ->and($byId->get('er:region:gash-barka')->code)->toBe('GB')
        ->and($byId->get('er:region:maekel')->code)->toBe('MA')
        ->and($byId->get('er:region:northern-red-sea')->code)->toBe('SK')
        ->and($byId->get('er:region:southern-red-sea')->code)->toBe('DK');
});

it('pins the verified ER tree of 11/7/14/10/12/4 subregions', function (): void {
    $areas = app(EritreaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('parentSourceId', 'er:region:anseba'))->toHaveCount(11)
        ->and($areas->where('parentSourceId', 'er:region:maekel'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'er:region:gash-barka'))->toHaveCount(14)
        ->and($areas->where('parentSourceId', 'er:region:northern-red-sea'))->toHaveCount(10)
        ->and($areas->where('parentSourceId', 'er:region:debub'))->toHaveCount(12)
        ->and($areas->where('parentSourceId', 'er:region:southern-red-sea'))->toHaveCount(4);

    // B9 fix carried forward: Debub's 12th subregion is Emni Haili
    // (UN OCHA COD ER610 + GeoNames ADM2 + two en-wp lists), not
    // Kudo Be'ur (only the "Subregions of Eritrea" list).
    expect($byId->get('er:subregion:emni-haili')->name)->toBe('Emni Haili')
        ->and($byId->get('er:subregion:emni-haili')->parentSourceId)->toBe('er:region:debub')
        ->and($byId->has('er:subregion:kudo-be-ur'))->toBeFalse();
});
