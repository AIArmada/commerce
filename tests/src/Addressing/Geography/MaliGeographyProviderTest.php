<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Mali\MaliAddressFormatter;
use AIArmada\Addressing\Geography\Mali\MaliGeographyProvider;

it('formats Malian addresses without a postcode system', function (): void {
    $formatted = app(MaliAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue 406 – porte 39',
        'line2' => 'Magnabougou',
        'city' => 'BAMAKO',
        'country_code' => 'ML',
    ]));

    expect($formatted)->toBe("Rue 406 – porte 39\nMagnabougou\nBAMAKO\nMali");
});
it('prints matching Malian city and region once', function (): void {
    $formatted = app(MaliAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue 10',
        'city' => 'Sikasso',
        'state' => 'Sikasso',
        'country_code' => 'ML',
    ]));

    expect($formatted)->toBe("Rue 10\nSikasso\nMali");
});

it('ships 19 regions plus Bamako with the national 9/10 numbering', function (): void {
    $areas = app(MaliGeographyProvider::class)->addressAreaSource()->areas();

    expect($areas)->toHaveCount(20);

    $byId = $areas->keyBy('sourceId');

    expect($byId->get('ml:region:menaka')->code)->toBe('10')
        ->and($byId->get('ml:region:taoudenit')->code)->toBe('9');

    $codes = $areas->mapWithKeys(fn ($area) => [$area->name => $area->code]);

    foreach (['Nioro' => '11', 'Kita' => '12', 'Dioila' => '13', 'Nara' => '14', 'Bougouni' => '15', 'Koutiala' => '16', 'San' => '17', 'Douentza' => '18', 'Bandiagara' => '19'] as $name => $code) {
        expect($codes->get($name))->toBe($code);
    }
});
