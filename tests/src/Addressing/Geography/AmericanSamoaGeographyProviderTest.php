<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\AmericanSamoa\AmericanSamoaAddressFormatter;
use AIArmada\Addressing\Geography\AmericanSamoa\AmericanSamoaGeographyProvider;

it('formats American Samoan addresses with the US ZIP layout', function (): void {
    $formatted = app(AmericanSamoaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO BOX 1234',
        'city' => 'PAGO PAGO',
        'postcode' => '96799',
        'country_code' => 'AS',
    ]));

    expect($formatted)->toBe("PO BOX 1234\nPAGO PAGO AS 96799\nAmerican Samoa");
});
it('formats American Samoan ZIP+4 codes', function (): void {
    $formatted = app(AmericanSamoaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO BOX 999',
        'city' => 'PAGO PAGO',
        'postcode' => '96799-1234',
        'country_code' => 'AS',
    ]));

    expect($formatted)->toBe("PO BOX 999\nPAGO PAGO AS 96799-1234\nAmerican Samoa");
});

it('ships 15 counties under districts with parent links', function (): void {
    $areas = app(AmericanSamoaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(15)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'as:district:western'))->toHaveCount(5)
        ->and($l2->where('parentSourceId', 'as:district:eastern'))->toHaveCount(5)
        ->and($l2->where('parentSourceId', 'as:district:manua'))->toHaveCount(5)
        ->and($byId->get('as:county:tau')->name)->toBe('Taʻū');
});
