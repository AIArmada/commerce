<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Monaco\MonacoGeographyProvider;

it('keeps the 17 ISO quarters rather than the 2013 ordinance wards', function (): void {
    $areas = app(MonacoGeographyProvider::class)->addressAreaSource()->areas();

    expect($areas)->toHaveCount(17)
        ->and($areas->firstWhere('sourceId', 'mc:quarter:la-colle')->code)->toBe('CL');
});
