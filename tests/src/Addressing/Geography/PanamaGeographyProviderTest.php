<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Panama\PanamaGeographyProvider;

it('spells the comarcas Ngäbe-Buglé and Guna Yala', function (): void {
    $areas = app(PanamaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('pa:indigenous_region:ngabe-bugle-comarca')->name)->toBe('Ngäbe-Buglé Comarca')
        ->and($areas->get('pa:indigenous_region:ngabe-bugle-comarca')->code)->toBe('NB')
        ->and($areas->get('pa:indigenous_region:guna-yala')->name)->toBe('Guna Yala')
        ->and($areas->get('pa:indigenous_region:guna-yala')->code)->toBe('KY')
        ->and($areas->has('pa:indigenous_region:ngobe-bugle-comarca'))->toBeFalse()
        ->and($areas->has('pa:indigenous_region:guna'))->toBeFalse();
});
