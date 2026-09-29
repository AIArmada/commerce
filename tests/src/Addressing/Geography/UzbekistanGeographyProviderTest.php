<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Uzbekistan\UzbekistanGeographyProvider;

it('spells the Xorazm tuman Tuproqqal\'a', function (): void {
    $areas = app(UzbekistanGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('uz:tuman:xorazm:tuproqqala')->name)->toBe('Tuproqqal\'a')
        ->and($areas->has('uz:tuman:xorazm:toproqqala'))->toBeFalse();
});
