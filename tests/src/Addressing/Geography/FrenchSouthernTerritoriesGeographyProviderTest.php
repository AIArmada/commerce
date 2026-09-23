<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\FrenchSouthernTerritories\FrenchSouthernTerritoriesAddressFormatter;
use AIArmada\Addressing\Geography\FrenchSouthernTerritories\FrenchSouthernTerritoriesGeographyProvider;

it('formats French Southern Territories addresses without a postcode system', function (): void {
    $formatted = app(FrenchSouthernTerritoriesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Base Alfred Faure',
        'city' => 'Port-aux-Français',
        'country_code' => 'TF',
    ]));

    expect($formatted)->toBe("Base Alfred Faure\nPort-aux-Français\nFrench Southern Territories");
});

it('prints any supplied Crozet code on its own line', function (): void {
    $formatted = app(FrenchSouthernTerritoriesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Base Alfred Faure',
        'city' => 'Port-aux-Français',
        'postcode' => '98400',
        'country_code' => 'TF',
    ]));

    expect($formatted)->toBe("Base Alfred Faure\nPort-aux-Français\n98400\nFrench Southern Territories");
});

it('ships the 5 districts as terminal states', function (): void {
    $areas = app(FrenchSouthernTerritoriesGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(5)
        ->and($areas->pluck('parentSourceId')->filter()->isEmpty())->toBeTrue()
        ->and($byId->get('tf:district:adelie-land')->name)->toBe('Adélie Land');
});
