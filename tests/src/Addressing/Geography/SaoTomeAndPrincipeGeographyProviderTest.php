<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SaoTomeAndPrincipe\SaoTomeAndPrincipeAddressFormatter;
use AIArmada\Addressing\Geography\SaoTomeAndPrincipe\SaoTomeAndPrincipeGeographyProvider;

it('formats Santomean addresses without a postcode system', function (): void {
    $formatted = app(SaoTomeAndPrincipeAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rua 3 de Fevereiro',
        'city' => 'São Tomé',
        'country_code' => 'ST',
    ]));

    expect($formatted)->toBe("Rua 3 de Fevereiro\nSão Tomé\nSao Tome and Principe");
});
it('prints any supplied Santomean code on its own line', function (): void {
    $formatted = app(SaoTomeAndPrincipeAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rua 3 de Fevereiro',
        'city' => 'São Tomé',
        'postcode' => '99999',
        'country_code' => 'ST',
    ]));

    expect($formatted)->toBe("Rua 3 de Fevereiro\nSão Tomé\n99999\nSao Tome and Principe");
});

it('ships 6 districts plus Príncipe as terminal states', function (): void {
    $areas = app(SaoTomeAndPrincipeGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(7)
        ->and($areas->pluck('parentSourceId')->filter()->isEmpty())->toBeTrue()
        ->and($byId->get('st:autonomous_region:principe')->name)->toBe('Príncipe');
});

it('labels tiers Distrito and Região Autónoma', function (): void {
    $provider = app(SaoTomeAndPrincipeGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['district' => 'Distrito', 'autonomous_region' => 'Região Autónoma'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
