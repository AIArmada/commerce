<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Angola\AngolaAddressFormatter;
use AIArmada\Addressing\Geography\Angola\AngolaGeographyProvider;

it('formats Angolan addresses without a postcode line', function (): void {
    $formatted = app(AngolaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rua Ndunduma 51',
        'city' => 'LUANDA',
        'country_code' => 'AO',
    ]));

    expect($formatted)->toBe("Rua Ndunduma 51\nLUANDA\nAngola");
});

it('ships the 21 operational provinces with Cuando Cubango retired', function (): void {
    $areas = app(AngolaGeographyProvider::class)->addressAreaSource()->areas();

    expect($areas)->toHaveCount(21);

    $byId = $areas->keyBy('sourceId');

    expect($byId->has('ao:province:cuando-cubango'))->toBeFalse()
        ->and($byId->get('ao:province:cuando')->code)->toBe('CUA')
        ->and($byId->get('ao:province:cubango')->code)->toBe('CUB')
        ->and($byId->get('ao:province:icolo-e-bengo')->name)->toBe('Icolo e Bengo')
        ->and($byId->get('ao:province:moxico-leste')->code)->toBe('MLE')
        ->and($byId->get('ao:province:cuanza-sul')->name)->toBe('Cuanza Sul')
        ->and($byId->has('ao:province:cuanza'))->toBeFalse();
});
