<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Russia\RussiaAddressFormatter;
use AIArmada\Addressing\Geography\Russia\RussiaGeographyProvider;

it('formats Russian addresses with the postcode below, country last', function (): void {
    $formatted = app(RussiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'ul. Lesnaya d. 5, kv.176',
        'city' => 'MOSKVA',
        'postcode' => '123456',
        'country_code' => 'RU',
    ]));

    expect($formatted)->toBe("ul. Lesnaya d. 5, kv.176\nMOSKVA\n123456\nRussia");
});

it('ships the 83 federal subjects as terminal states', function (): void {
    $areas = app(RussiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(83)
        ->and($areas->pluck('parentSourceId')->filter()->isEmpty())->toBeTrue()
        ->and($areas->where('type', 'oblast'))->toHaveCount(46)
        ->and($areas->where('type', 'republic'))->toHaveCount(21)
        ->and($byId->get('ru:krai:altai-krai')->name)->toBe('Altai Krai');
});
