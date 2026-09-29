<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Estonia\EstoniaGeographyProvider;

it('exposes corrected Estonian municipality names', function (): void {
    $areas = app(EstoniaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('ee:rural_municipality:noo')->name)->toBe('Nõo')
        ->and($areas->get('ee:rural_municipality:noo')->code)->toBe('528')
        ->and($areas->get('ee:rural_municipality:pohja-parnumaa')->name)->toBe('Põhja-Pärnumaa')
        ->and($areas->get('ee:rural_municipality:pohja-parnumaa')->code)->toBe('638')
        ->and($areas->get('ee:rural_municipality:pohja-parnumaa')->type)->toBe('rural_municipality')
        ->and($areas->get('ee:rural_municipality:poltsamaa')->name)->toBe('Põltsamaa')
        ->and($areas->get('ee:rural_municipality:joelahtme')->name)->toBe('Jõelähtme')
        ->and($areas->get('ee:rural_municipality:joelahtme')->code)->toBe('245')
        ->and($areas->has('ee:rural_municipality:pohja-parnu'))->toBeFalse();
});
