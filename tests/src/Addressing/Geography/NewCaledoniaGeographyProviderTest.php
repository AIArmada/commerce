<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\NewCaledonia\NewCaledoniaAddressFormatter;
use AIArmada\Addressing\Geography\NewCaledonia\NewCaledoniaGeographyProvider;

it('formats New Caledonian addresses with the code left of the locality', function (): void {
    $formatted = app(NewCaledoniaAddressFormatter::class)->format(AddressData::from([
        'line1' => '24 RUE DES PALMIERS',
        'city' => 'NOUMEA',
        'postcode' => '98800',
        'country_code' => 'NC',
    ]));

    expect($formatted)->toBe("24 RUE DES PALMIERS\n98800 NOUMEA\nNew Caledonia");
});
it('formats Mont-Dore addresses with their own code', function (): void {
    $formatted = app(NewCaledoniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 485',
        'city' => 'MONT-DORE',
        'postcode' => '98810',
        'country_code' => 'NC',
    ]));

    expect($formatted)->toBe("BP 485\n98810 MONT-DORE\nNew Caledonia");
});

it('ships 33 communes under provinces with parent links', function (): void {
    $areas = app(NewCaledoniaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'commune');

    expect($l2)->toHaveCount(33)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('nc:commune:noumea')->name)->toBe('Nouméa')
        ->and($byId->get('nc:commune:poya')->name)->toBe('Poya')
        ->and($byId->get('nc:commune:lifou')->name)->toBe('Lifou');
});

it('labels tiers Province and Commune', function (): void {
    $provider = app(NewCaledoniaGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['province' => 'Province', 'commune' => 'Commune'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
