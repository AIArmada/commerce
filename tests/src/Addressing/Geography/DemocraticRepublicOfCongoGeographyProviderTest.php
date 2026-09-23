<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\DemocraticRepublicOfCongo\DemocraticRepublicOfCongoAddressFormatter;
use AIArmada\Addressing\Geography\DemocraticRepublicOfCongo\DemocraticRepublicOfCongoGeographyProvider;

it('formats Congolese addresses with the postcode left of the province', function (): void {
    $formatted = app(DemocraticRepublicOfCongoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Avenue de la Poste N°1',
        'city' => 'LIMETE',
        'state' => 'KINSHASA',
        'postcode' => '1004131',
        'country_code' => 'CD',
    ]));

    expect($formatted)->toBe("Avenue de la Poste N°1\nLIMETE\n1004131 KINSHASA\nDemocratic Republic of the Congo");
});

it('ships 145 territories under provinces with parent links', function (): void {
    $areas = app(DemocraticRepublicOfCongoGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'territory');

    expect($l2)->toHaveCount(145)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('cd:territory:aketi')->name)->toBe('Aketi')
        ->and($byId->get('cd:territory:bagata')->name)->toBe('Bagata')
        ->and($byId->get('cd:territory:beni')->name)->toBe('Beni');
});

it('labels tiers Province and Territoire', function (): void {
    $provider = app(DemocraticRepublicOfCongoGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['province' => 'Province', 'territory' => 'Territoire'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
