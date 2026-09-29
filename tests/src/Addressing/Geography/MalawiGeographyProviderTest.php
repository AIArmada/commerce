<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Malawi\MalawiGeographyProvider;

it('ships 28 districts under regions with parent links', function (): void {
    $areas = app(MalawiGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($l2)->toHaveCount(28)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('mw:district:balaka')->name)->toBe('Balaka')
        ->and($byId->get('mw:district:blantyre')->parentSourceId)->toBe('mw:region:southern');
});
