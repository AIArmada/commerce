<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Mauritius\MauritiusAddressFormatter;
use AIArmada\Addressing\Geography\Mauritius\MauritiusGeographyProvider;

it('formats Mauritian addresses with the postcode right of the locality', function (): void {
    $formatted = app(MauritiusAddressFormatter::class)->format(AddressData::from([
        'line1' => '10, rue Claude Delaître',
        'line2' => 'Les Guibies',
        'city' => 'PORT LOUIS',
        'postcode' => '11213',
        'country_code' => 'MU',
    ]));

    expect($formatted)->toBe("10, rue Claude Delaître\nLes Guibies\nPORT LOUIS 11213\nMauritius");
});
it('formats Rodriguan addresses with the R postcode right of the locality', function (): void {
    $formatted = app(MauritiusAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de la Solidarité',
        'city' => 'Port Mathurin',
        'state' => 'Rodrigues Island',
        'postcode' => 'R5135',
        'country_code' => 'MU',
    ]));

    expect($formatted)->toBe("Rue de la Solidarité\nPort Mathurin R5135\nRodrigues Island\nMauritius");
});

it('ships 142 cities, towns and villages under districts with parent links', function (): void {
    $areas = app(MauritiusGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(142)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('mu:city:port-louis')->name)->toBe('Port Louis')
        ->and($byId->get('mu:town:curepipe')->name)->toBe('Curepipe')
        ->and($byId->get('mu:village:chamarel')->name)->toBe('Chamarel')
        ->and($byId->get('mu:village:vingt-cinq')->name)->toBe('Vingt-Cinq');
});
