<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Jordan\JordanGeographyProvider;

it('ships 51 liwa under governorates with parent links', function (): void {
    $areas = app(JordanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'liwa');

    expect($l2)->toHaveCount(51)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('jo:liwa:ajloun:ajloun-qasabah')->name)->toBe('Ajloun Qasabah')
        ->and($byId->get('jo:liwa:ajloun:kufranjah')->parentSourceId)->toBe('jo:governorate:ajloun');
});
