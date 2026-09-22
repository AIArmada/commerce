<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\WallisAndFutuna\WallisAndFutunaAddressFormatter;
use AIArmada\Addressing\Geography\WallisAndFutuna\WallisAndFutunaGeographyProvider;

it('formats Wallis and Futuna addresses with the code left of the locality', function (): void {
    $formatted = app(WallisAndFutunaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Route de Mata-Utu',
        'city' => 'MATA-UTU',
        'postcode' => '98600',
        'country_code' => 'WF',
    ]));

    expect($formatted)->toBe("Route de Mata-Utu\n98600 MATA-UTU\nWallis and Futuna Islands");
});

it('formats Futuna addresses with their own code', function (): void {
    $formatted = app(WallisAndFutunaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 5',
        'city' => 'LEAVA',
        'postcode' => '98620',
        'country_code' => 'WF',
    ]));

    expect($formatted)->toBe("BP 5\n98620 LEAVA\nWallis and Futuna Islands");
});

it('ships 3 districts under Uvea with Alo and Sigave childless', function (): void {
    $areas = app(WallisAndFutunaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(3)
        ->and($l2->pluck('parentSourceId')->unique()->all())->toBe(['wf:administrative_precinct:uvea'])
        ->and($byId->get('wf:district:mu-a')->name)->toBe("Mu'a");
});
