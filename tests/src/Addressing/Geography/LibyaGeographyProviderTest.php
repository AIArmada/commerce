<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Libya\LibyaGeographyProvider;

it('ships 100 DTM baladiyas under popularates with parent links', function (): void {
    $areas = app(LibyaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(100)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'ly:popularate:jabal-al-gharbi'))->toHaveCount(14)
        ->and($l2->where('parentSourceId', 'ly:popularate:tripoli'))->toHaveCount(6)
        ->and($l2->where('parentSourceId', 'ly:popularate:al-wahat'))->toHaveCount(6)
        ->and($byId->get('ly:baladiya:tajoura')->code)->toBe('LY021102')
        ->and($byId->get('ly:baladiya:bani-waleed')->parentSourceId)->toBe('ly:popularate:misrata');
});
