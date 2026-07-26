<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\FormatAddressAction;
use AIArmada\Addressing\Data\AddressData;

it('formats Malaysian addresses using the country formatter', function (): void {
    $address = AddressData::from([
        'line1' => 'Lot 12 Jalan Mawar',
        'city' => 'Kajang',
        'state' => 'Selangor',
        'postcode' => '43000',
        'countryCode' => 'MY',
        'components' => ['district' => 'Hulu Langat'],
    ]);

    expect(app(FormatAddressAction::class)->format($address))->toBe(implode("\n", [
        'Lot 12 Jalan Mawar',
        'Hulu Langat',
        '43000 Kajang',
        'Selangor',
        'MY',
    ]));
});

it('falls back to the generic formatter for countries without a formatter', function (): void {
    $address = AddressData::from([
        'line1' => '10 Downing Street',
        'city' => 'London',
        'state' => 'London',
        'postcode' => 'SW1A 2AA',
        'countryCode' => 'GB',
    ]);

    expect(app(FormatAddressAction::class)->format($address))->toBe(implode("\n", [
        '10 Downing Street',
        'SW1A 2AA London, London',
        'GB',
    ]));
});
