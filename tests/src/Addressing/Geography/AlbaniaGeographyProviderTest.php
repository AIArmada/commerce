<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Albania\AlbaniaAddressFormatter;

it('formats Albanian addresses with the postcode above the locality', function (): void {
    $formatted = app(AlbaniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Ruga Myslym Shyri',
        'line2' => 'Pallati 37 shkalla 4 apartamenti 15',
        'city' => 'TIRANA',
        'postcode' => '1001',
        'country_code' => 'AL',
    ]));

    expect($formatted)->toBe("Ruga Myslym Shyri\nPallati 37 shkalla 4 apartamenti 15\n1001\nTIRANA\nAlbania");
});
it('formats Albanian addresses keeping the county below the locality', function (): void {
    $formatted = app(AlbaniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Ruga Myslym Shyri',
        'city' => 'Tirana',
        'state' => 'Tirana',
        'postcode' => '1001',
        'country_code' => 'AL',
    ]));

    expect($formatted)->toBe("Ruga Myslym Shyri\n1001\nTirana\nAlbania");
});
