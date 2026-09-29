<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Moldova\MoldovaGeographyProvider;

it('ships 981 communes and cities under districts with parent links', function (): void {
    $areas = app(MoldovaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(981)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('type', 'city'))->toHaveCount(66)
        ->and($l2->where('type', 'commune'))->toHaveCount(915)
        ->and($l2->where('parentSourceId', 'md:territorial_unit:transnistria'))->toHaveCount(79)
        ->and($byId->get('md:city:donduseni')->name)->toBe('Dondușeni (city)')
        ->and($byId->get('md:commune:donduseni')->parentSourceId)->toBe('md:district:donduseni');
});
