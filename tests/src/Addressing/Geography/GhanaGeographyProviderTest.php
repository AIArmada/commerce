<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Ghana\GhanaAddressFormatter;
use AIArmada\Addressing\Geography\Ghana\GhanaGeographyProvider;

it('formats Ghanaian addresses with the postcode right and region below', function (): void {
    $formatted = app(GhanaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P. O. BOX GP 224',
        'city' => 'Accra-Central',
        'state' => 'GREATER ACCRA',
        'postcode' => 'GA-183-8164',
        'country_code' => 'GH',
    ]));

    expect($formatted)->toBe("P. O. BOX GP 224\nAccra-Central GA-183-8164\nGREATER ACCRA\nGhana");
});

it('ships 261 metropolitan, municipal and district assemblies with parent links', function (): void {
    $areas = app(GhanaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(261)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($areas->where('type', 'metropolitan_city'))->toHaveCount(6)
        ->and($byId->get('gh:metropolitan_city:kumasi')->name)->toBe('Kumasi')
        ->and($byId->get('gh:municipality:asunafo-north')->name)->toBe('Asunafo North')
        ->and($byId->get('gh:district:birim-north')->name)->toBe('Birim North');
});
