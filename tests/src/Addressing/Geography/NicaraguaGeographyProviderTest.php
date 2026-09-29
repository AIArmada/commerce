<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Nicaragua\NicaraguaGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('names the autonomous regions Costa Caribe with English aliases', function (): void {
    $areas = app(NicaraguaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('ni:autonomous_region:costa-caribe-norte')->name)->toBe('Costa Caribe Norte')
        ->and($areas->get('ni:autonomous_region:costa-caribe-norte')->code)->toBe('AN')
        ->and($areas->get('ni:autonomous_region:costa-caribe-sur')->name)->toBe('Costa Caribe Sur')
        ->and($areas->get('ni:autonomous_region:costa-caribe-sur')->code)->toBe('AS')
        ->and($areas->has('ni:autonomous_region:north-caribbean-coast'))->toBeFalse()
        ->and($areas->has('ni:autonomous_region:south-caribbean-coast'))->toBeFalse();

    $names = app(NicaraguaGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names['ni:autonomous_region:costa-caribe-norte'][0]['name'])->toBe('North Caribbean Coast')
        ->and($names['ni:autonomous_region:costa-caribe-sur'][0]['name'])->toBe('South Caribbean Coast');
});
