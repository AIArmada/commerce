<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Iraq\IraqAddressFormatter;
use AIArmada\Addressing\Geography\Iraq\IraqGeographyProvider;

it('formats Iraqi addresses with city, governorate and postcode below', function (): void {
    $formatted = app(IraqAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Hay AL Asmaee, Zukak 2',
        'city' => 'AL ASMAEE',
        'state' => 'AL BASRAH',
        'postcode' => '61002',
        'country_code' => 'IQ',
    ]));

    expect($formatted)->toBe("Hay AL Asmaee, Zukak 2\nAL ASMAEE, AL BASRAH\n61002\nIraq");
});

it('ships 119 districts under governorates with parent links', function (): void {
    $areas = app(IraqGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($l2)->toHaveCount(119)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('iq:district:mosul')->name)->toBe('Mosul')
        ->and($byId->get('iq:district:abu-ghraib')->name)->toBe('Abu Ghraib')
        ->and($byId->get('iq:district:makhmur')->name)->toBe('Makhmur');
});

it('labels tiers Muhafaza and Qadaa', function (): void {
    $provider = app(IraqGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['governorate' => 'Muhafaza', 'district' => 'Qadaa'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
