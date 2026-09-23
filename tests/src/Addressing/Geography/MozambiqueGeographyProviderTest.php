<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Mozambique\MozambiqueAddressFormatter;
use AIArmada\Addressing\Geography\Mozambique\MozambiqueGeographyProvider;

it('formats Mozambican addresses with the postcode left and province below', function (): void {
    $formatted = app(MozambiqueAddressFormatter::class)->format(AddressData::from([
        'line1' => 'AV. Julius Nyerere 3412',
        'city' => 'MAPUTO',
        'state' => 'MAPUTO',
        'postcode' => '1100',
        'country_code' => 'MZ',
    ]));

    expect($formatted)->toBe("AV. Julius Nyerere 3412\n1100 MAPUTO\nMAPUTO\nMozambique");
});

it('ships 136 districts under provinces with parent links', function (): void {
    $areas = app(MozambiqueGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($l2)->toHaveCount(136)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('mz:district:kampfumo')->name)->toBe('KaMpfumo')
        ->and($byId->get('mz:district:doa')->name)->toBe('Doa')
        ->and($byId->get('mz:district:guro')->name)->toBe('Guro')
        ->and($byId->get('mz:district:ile')->name)->toBe('Ile');
});

it('labels tiers Província, Cidade and Distrito', function (): void {
    $provider = app(MozambiqueGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['province' => 'Província', 'city' => 'Cidade', 'district' => 'Distrito'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
