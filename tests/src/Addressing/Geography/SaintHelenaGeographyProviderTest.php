<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\SaintHelena\SaintHelenaGeographyProvider;

it('ships 8 districts plus Ascension and Tristan da Cunha', function (): void {
    $areas = app(SaintHelenaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas)->toHaveCount(10)
        ->and($areas->get('sh:island:ascension')->code)->toBe('AC')
        ->and($areas->get('sh:island:tristan-da-cunha')->code)->toBe('TA')
        ->and($areas->get('sh:district:jamestown')->type)->toBe('district');
});
