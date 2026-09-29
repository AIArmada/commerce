<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Maldives\MaldivesGeographyProvider;

it('ships 18 atolls and 5 cities with Malé typed as a city', function (): void {
    $areas = app(MaldivesGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->where('level', 1))->toHaveCount(23)
        ->and($areas->where('type', 'atoll'))->toHaveCount(18)
        ->and($areas->where('type', 'city'))->toHaveCount(5)
        ->and($areas->get('mv:city:male')->code)->toBe('MLE')
        ->and($areas->get('mv:city:fuvahmulah')->code)->toBe('FVM')
        ->and($areas->get('mv:city:kulhudhuffushi')->code)->toBe('KUH')
        ->and($areas->get('mv:city:thinadhoo')->code)->toBe('THD')
        ->and($areas->has('mv:atoll:male'))->toBeFalse()
        ->and($areas->has('mv:atoll:gnaviyani'))->toBeFalse();
});
