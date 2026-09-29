<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\EquatorialGuinea\EquatorialGuineaGeographyProvider;

it('ships 8 provinces under regions with parent links', function (): void {
    $areas = app(EquatorialGuineaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'province');

    expect($l2)->toHaveCount(8)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('gq:province:annobon')->name)->toBe('Annobón')
        ->and($byId->get('gq:province:djibloho')->parentSourceId)->toBe('gq:region:rio-muni');
});
