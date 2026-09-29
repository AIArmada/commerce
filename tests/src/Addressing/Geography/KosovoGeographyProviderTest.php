<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Kosovo\KosovoGeographyProvider;

it('exposes the corrected Gjakova district slug and name', function (): void {
    $areas = app(KosovoGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('xk:district:gjakova')->name)->toBe('Gjakova')
        ->and($areas->get('xk:district:gjakova')->code)->toBe('XDG')
        ->and($areas->get('xk:district:gjakova')->type)->toBe('district')
        ->and($areas->has('xk:district:gjakove'))->toBeFalse();
});
