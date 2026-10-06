<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Bahamas\BahamasGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('uses ISO district names with aliases for the renamed districts', function (): void {
    $areas = app(BahamasGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas)->toHaveCount(32)
        ->and($areas->get('bs:district:crooked-island-and-long-cay')->name)->toBe('Crooked Island and Long Cay')
        ->and($areas->get('bs:district:city-of-freeport')->name)->toBe('City of Freeport')
        ->and($areas->get('bs:district:san-salvador')->name)->toBe('San Salvador')
        ->and($areas->get('bs:island:new-providence')->type)->toBe('island')
        ->and($areas->has('bs:district:crooked-island'))->toBeFalse()
        ->and($areas->has('bs:district:freeport'))->toBeFalse()
        ->and($areas->has('bs:district:san-salvador-island'))->toBeFalse()
        ->and($areas->has('bs:district:new-providence'))->toBeFalse();

    $names = app(BahamasGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names['bs:district:crooked-island-and-long-cay'][0]['name'])->toBe('Crooked Island')
        ->and($names['bs:district:city-of-freeport'][0]['name'])->toBe('Freeport')
        ->and($names['bs:district:san-salvador'][0]['name'])->toBe('San Salvador Island');
});

it('pins the verified Bahamas tree of 32 ISO subdivisions', function (): void {
    $areas = app(BahamasGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    // ISO 3166-2:BS current codes: 1 island + 31 districts; retired
    // codes (BS-SP etc.) appear only in the ISO Changes section. No
    // postcode system per the UPU bhs profile, so no postal files ship.
    expect($areas)->toHaveCount(32)
        ->and($byId->get('bs:island:new-providence')->code)->toBe('NP')
        ->and($byId->get('bs:district:acklins')->code)->toBe('AK')
        ->and($byId->get('bs:district:central-abaco')->code)->toBe('CO')
        ->and($byId->get('bs:district:city-of-freeport')->code)->toBe('FP')
        ->and($byId->get('bs:district:exuma')->code)->toBe('EX')
        ->and($byId->get('bs:district:harbour-island')->code)->toBe('HI')
        ->and($byId->get('bs:district:inagua')->code)->toBe('IN')
        ->and($byId->get('bs:district:long-island')->code)->toBe('LI')
        ->and($byId->get('bs:district:mayaguana')->code)->toBe('MG')
        ->and($byId->get('bs:district:north-eleuthera')->code)->toBe('NE')
        ->and($byId->get('bs:district:ragged-island')->code)->toBe('RI')
        ->and($byId->get('bs:district:san-salvador')->code)->toBe('SS')
        ->and($byId->get('bs:district:south-andros')->code)->toBe('SA')
        ->and($byId->get('bs:district:spanish-wells')->code)->toBe('SW')
        ->and($byId->get('bs:district:west-grand-bahama')->code)->toBe('WG');
});
