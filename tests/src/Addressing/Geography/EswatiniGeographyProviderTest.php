<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Eswatini\EswatiniAddressFormatter;
use AIArmada\Addressing\Geography\Eswatini\EswatiniGeographyProvider;

it('formats Eswatini addresses with the postcode below the locality', function (): void {
    $formatted = app(EswatiniAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 125',
        'city' => 'MBABANE',
        'postcode' => 'H100',
        'country_code' => 'SZ',
    ]));

    expect($formatted)->toBe("P.O. Box 125\nMBABANE\nH100\nEswatini");
});
it('prints matching Eswatini city and region once above the postcode', function (): void {
    $formatted = app(EswatiniAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 200',
        'city' => 'Manzini',
        'state' => 'Manzini',
        'postcode' => 'M200',
        'country_code' => 'SZ',
    ]));

    expect($formatted)->toBe("P.O. Box 200\nManzini\nM200\nEswatini");
});

it('ships 55 inkhundlas under regions with parent links', function (): void {
    $areas = app(EswatiniGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'inkhundla');

    expect($l2)->toHaveCount(55)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('sz:inkhundla:lobamba')->name)->toBe('Lobamba')
        ->and($byId->get('sz:inkhundla:mbabane-west')->name)->toBe('Mbabane West')
        ->and($byId->get('sz:inkhundla:hlane')->name)->toBe('Hlane');
});
