<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Tonga\TongaAddressFormatter;
use AIArmada\Addressing\Geography\Tonga\TongaGeographyProvider;

it('formats Tongan addresses without a postcode system', function (): void {
    $formatted = app(TongaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Nuku’alofa',
        'country_code' => 'TO',
    ]));

    expect($formatted)->toBe("PO Box 1\nNuku’alofa\nTonga");
});

it('prints any supplied Tongan code on its own line', function (): void {
    $formatted = app(TongaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Nuku’alofa',
        'postcode' => '99999',
        'country_code' => 'TO',
    ]));

    expect($formatted)->toBe("PO Box 1\nNuku’alofa\n99999\nTonga");
});

it('ships 23 districts under divisions with parent links', function (): void {
    $areas = app(TongaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(23)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('to:district:kolofoou')->code)->toBe('TO-041')
        ->and($byId->get('to:district:haano')->code)->toBe('TO-025')
        ->and($byId->get('to:district:eua-motua')->parentSourceId)->toBe('to:division:eua')
        ->and($byId->get('to:district:niua-foou')->parentSourceId)->toBe('to:division:niuas');
});
