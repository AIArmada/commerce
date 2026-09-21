<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Mexico\MexicoAddressFormatter;
use AIArmada\Addressing\Geography\Mexico\MexicoGeographyProvider;

it('formats Mexican addresses with the postcode left and abbreviation after', function (): void {
    $formatted = app(MexicoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Insurgentes Sur 1602',
        'city' => 'MEXICO',
        'state' => 'Ciudad de México',
        'postcode' => '02860',
        'country_code' => 'MX',
    ]));

    expect($formatted)->toBe("Insurgentes Sur 1602\n02860 MEXICO, CDMX\nMexico");
});

it('ships 2,479 municipalities and boroughs under their states', function (): void {
    $areas = app(MexicoGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas)->toHaveCount(2511)
        ->and($areas->where('level', 1))->toHaveCount(32)
        ->and($areas->where('level', 2))->toHaveCount(2479)
        ->and($areas->where('type', 'borough'))->toHaveCount(16)
        ->and($areas->where('parentSourceId', 'mx:state:oaxaca'))->toHaveCount(570)
        ->and($areas->where('parentSourceId', 'mx:state:puebla'))->toHaveCount(217)
        ->and($areas->where('parentSourceId', 'mx:state:sinaloa'))->toHaveCount(20)
        ->and($areas->where('parentSourceId', 'mx:state:aguascalientes'))->toHaveCount(12)
        ->and($areas->where('parentSourceId', 'mx:state:san-luis-potosi'))->toHaveCount(59);

    $byId = $areas->keyBy->sourceId;

    expect($byId->get('mx:municipality:25019')->name)->toBe('Eldorado')
        ->and($byId->get('mx:municipality:25020')->name)->toBe('Juan José Ríos')
        ->and($byId->get('mx:municipality:24059')->name)->toBe('Villa de Pozos')
        ->and($byId->get('mx:municipality:01012')->name)->toBe('Villa Juárez')
        ->and($byId->get('mx:borough:09002')->name)->toBe('Azcapotzalco')
        ->and($byId->get('mx:municipality:08008')->name)->toBe('Batopilas de Manuel Gómez Morín');
});
