<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\TrinidadAndTobago\TrinidadAndTobagoGeographyProvider;

it('types Diego Martin and Siparia as boroughs and San Fernando as a city', function (): void {
    $areas = app(TrinidadAndTobagoGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas)->toHaveCount(15)
        ->and($areas->get('tt:borough:diego-martin')->code)->toBe('DMN')
        ->and($areas->get('tt:borough:siparia')->code)->toBe('SIP')
        ->and($areas->get('tt:city:san-fernando')->code)->toBe('SFO')
        ->and($areas->has('tt:region:diego-martin'))->toBeFalse()
        ->and($areas->has('tt:region:san-fernando'))->toBeFalse()
        ->and($areas->has('tt:region:siparia'))->toBeFalse();
});
