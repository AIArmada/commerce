<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Thailand\ThailandAddressFormatter;
use AIArmada\Addressing\Geography\Thailand\ThailandGeographyProvider;

it('formats Thai addresses with district, province and postcode below', function (): void {
    $formatted = app(ThailandAddressFormatter::class)->format(AddressData::from([
        'line1' => '199/63 Moo 1, Tumbol Bangtalad',
        'city' => 'Amphoe Pak Kret',
        'state' => 'Nonthaburi',
        'postcode' => '11120',
        'country_code' => 'TH',
    ]));

    expect($formatted)->toBe("199/63 Moo 1, Tumbol Bangtalad\nAmphoe Pak Kret, Nonthaburi\n11120\nThailand");
});

it('ships 76 provinces plus Bangkok with no Pattaya row', function (): void {
    $areas = app(ThailandGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas)->toHaveCount(77)
        ->and($areas->pluck('code'))->not->toContain('S');
});
