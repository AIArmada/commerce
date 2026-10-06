<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Jamaica\JamaicaGeographyProvider;

it('pins the verified Jamaica tree of 14 parishes', function (): void {
    $areas = app(JamaicaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'parish'))->toHaveCount(14)
        ->and($areas->where('level', 1))->toHaveCount(14);

    // ISO 3166-2:JM codes, exact set. No postcode system per the UPU
    // jam profile (Kingston sector codes are not postcodes), so no
    // postal files ship.
    expect($byId->get('jm:parish:kingston')->code)->toBe('01')
        ->and($byId->get('jm:parish:saint-andrew')->code)->toBe('02')
        ->and($byId->get('jm:parish:saint-thomas')->code)->toBe('03')
        ->and($byId->get('jm:parish:portland')->code)->toBe('04')
        ->and($byId->get('jm:parish:saint-mary')->code)->toBe('05')
        ->and($byId->get('jm:parish:saint-ann')->code)->toBe('06')
        ->and($byId->get('jm:parish:trelawny')->code)->toBe('07')
        ->and($byId->get('jm:parish:saint-james')->code)->toBe('08')
        ->and($byId->get('jm:parish:hanover')->code)->toBe('09')
        ->and($byId->get('jm:parish:westmoreland')->code)->toBe('10')
        ->and($byId->get('jm:parish:saint-elizabeth')->code)->toBe('11')
        ->and($byId->get('jm:parish:manchester')->code)->toBe('12')
        ->and($byId->get('jm:parish:clarendon')->code)->toBe('13')
        ->and($byId->get('jm:parish:saint-catherine')->code)->toBe('14');
});
