<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Madagascar\MadagascarGeographyProvider;

it('ships 114 districts under regions with parent links', function (): void {
    $areas = app(MadagascarGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l3 = $areas->where('type', 'district');

    expect($l3)->toHaveCount(114)
        ->and($l3->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('mg:district:antananarivo-renivohitra')->name)->toBe('Antananarivo-Renivohitra')
        ->and($byId->get('mg:district:toamasina-i')->parentSourceId)->toBe('mg:region:atsinanana')
        ->and($byId->get('mg:district:maroantsetra')->parentSourceId)->toBe('mg:region:ambatosoa');
});
