<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\FrenchPolynesia\FrenchPolynesiaAddressFormatter;
use AIArmada\Addressing\Geography\FrenchPolynesia\FrenchPolynesiaGeographyProvider;

it('formats French Polynesian addresses with the code left of the locality', function (): void {
    $formatted = app(FrenchPolynesiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 123',
        'city' => 'PAPEETE',
        'postcode' => '98714',
        'country_code' => 'PF',
    ]));

    expect($formatted)->toBe("BP 123\n98714 PAPEETE\nFrench Polynesia");
});

it('formats Faaa addresses with their own code', function (): void {
    $formatted = app(FrenchPolynesiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de l’Aéroport',
        'city' => 'FAAA',
        'postcode' => '98704',
        'country_code' => 'PF',
    ]));

    expect($formatted)->toBe("Rue de l’Aéroport\n98704 FAAA\nFrench Polynesia");
});

it('ships 48 communes under divisions with parent links', function (): void {
    $areas = app(FrenchPolynesiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'commune');

    expect($l2)->toHaveCount(48)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('pf:commune:papeete')->name)->toBe('Papeete')
        ->and($byId->get('pf:commune:fatu-hiva')->name)->toBe('Fatu-Hiva')
        ->and($byId->get('pf:commune:ua-pou')->name)->toBe('Ua-Pou');
});
