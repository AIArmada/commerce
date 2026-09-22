<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Uruguay\UruguayAddressFormatter;
use AIArmada\Addressing\Geography\Uruguay\UruguayGeographyProvider;

it('formats Uruguayan addresses with the postcode and dash left of the locality', function (): void {
    $formatted = app(UruguayAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Chaná 1215, apto. 152',
        'city' => 'ROSARIO',
        'state' => 'COLONIA',
        'postcode' => '70200',
        'country_code' => 'UY',
    ]));

    expect($formatted)->toBe("Chaná 1215, apto. 152\n70200 – ROSARIO\nCOLONIA\nUruguay");
});
it('formats Uruguayan capital addresses with the Montevideo postcode', function (): void {
    $formatted = app(UruguayAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Av. 18 de Julio 1000',
        'city' => 'MONTEVIDEO',
        'postcode' => '11600',
        'country_code' => 'UY',
    ]));

    expect($formatted)->toBe("Av. 18 de Julio 1000\n11600 – MONTEVIDEO\nUruguay");
});

it('ships 125 municipalitys under departments with parent links', function (): void {
    $areas = app(UruguayGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'municipality');

    expect($l2)->toHaveCount(125)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('uy:municipality:municipality-a')->name)->toBe('Municipality A')
        ->and($byId->get('uy:municipality:maldonado')->name)->toBe('Maldonado')
        ->and($byId->get('uy:municipality:ciudad-de-la-costa')->name)->toBe('Ciudad de la Costa');
});
