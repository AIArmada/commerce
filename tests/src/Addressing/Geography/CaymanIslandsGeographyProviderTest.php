<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\CaymanIslands\CaymanIslandsAddressFormatter;
use AIArmada\Addressing\Geography\CaymanIslands\CaymanIslandsGeographyProvider;

it('formats Caymanian addresses with the postcode right of the island', function (): void {
    $formatted = app(CaymanIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 802',
        'state' => 'Grand Cayman',
        'postcode' => 'KY1-1103',
        'country_code' => 'KY',
    ]));

    expect($formatted)->toBe("P.O. Box 802\nGrand Cayman  KY1-1103\nCayman Islands");
});
it('formats Cayman Brac addresses with the Brac postcode', function (): void {
    $formatted = app(CaymanIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 12',
        'state' => 'Cayman Brac',
        'postcode' => 'KY2-2100',
        'country_code' => 'KY',
    ]));

    expect($formatted)->toBe("P.O. Box 12\nCayman Brac  KY2-2100\nCayman Islands");
});

it('ships 7 districts under islands with parent links', function (): void {
    $areas = app(CaymanIslandsGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(7)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'ky:island:grand-cayman'))->toHaveCount(5)
        ->and($byId->get('ky:district:george-town')->parentSourceId)->toBe('ky:island:grand-cayman')
        ->and($byId->get('ky:district:cayman-brac')->parentSourceId)->toBe('ky:island:cayman-brac');
});
