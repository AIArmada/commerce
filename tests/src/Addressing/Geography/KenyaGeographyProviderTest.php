<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Kenya\KenyaAddressFormatter;
use AIArmada\Addressing\Geography\Kenya\KenyaGeographyProvider;

it('formats Kenyan addresses with the postcode below, then town', function (): void {
    $formatted = app(KenyaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P O BOX 2784 – NAKURU GPO',
        'city' => 'NAKURU',
        'state' => 'Nakuru',
        'postcode' => '20100',
        'country_code' => 'KE',
    ]));

    expect($formatted)->toBe("P O BOX 2784 – NAKURU GPO\n20100\nNAKURU\nKenya");
});

it('ships 290 constituencies under counties with parent links', function (): void {
    $areas = app(KenyaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'constituency');

    expect($l2)->toHaveCount(290)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('ke:constituency:westlands')->name)->toBe('Westlands')
        ->and($byId->get('ke:constituency:garissa-township')->name)->toBe('Garissa Township')
        ->and($byId->get('ke:constituency:bomet-central')->name)->toBe('Bomet Central');
});
