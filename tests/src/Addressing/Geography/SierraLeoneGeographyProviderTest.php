<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SierraLeone\SierraLeoneAddressFormatter;

it('formats Sierra Leonean addresses without a postcode system', function (): void {
    $formatted = app(SierraLeoneAddressFormatter::class)->format(AddressData::from([
        'line1' => '7A Ross Road Cline',
        'city' => 'FREETOWN',
        'country_code' => 'SL',
    ]));

    expect($formatted)->toBe("7A Ross Road Cline\nFREETOWN\nSierra Leone");
});
it('formats Sierra Leonean addresses with the province below the locality', function (): void {
    $formatted = app(SierraLeoneAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Bojon Street',
        'city' => 'Bo',
        'state' => 'Southern',
        'country_code' => 'SL',
    ]));

    expect($formatted)->toBe("Bojon Street\nBo\nSouthern\nSierra Leone");
});
