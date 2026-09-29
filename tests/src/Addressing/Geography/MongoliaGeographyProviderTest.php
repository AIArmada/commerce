<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Mongolia\MongoliaGeographyProvider;

it('codes Ulaanbaatar as single digit 1 per ISO MN-1', function (): void {
    $areas = app(MongoliaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('mn:capital_city:ulaanbaatar')->code)->toBe('1');
});
