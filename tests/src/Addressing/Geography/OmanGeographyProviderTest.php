<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Oman\OmanGeographyProvider;

it('ships 63 wilayats under governorates with parent links', function (): void {
    $areas = app(OmanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'wilayat');

    expect($l2)->toHaveCount(63)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('om:wilayat:adam')->name)->toBe('Adam')
        ->and($byId->get('om:wilayat:al-hamra')->parentSourceId)->toBe('om:governorate:ad-dakhiliyah');
});
