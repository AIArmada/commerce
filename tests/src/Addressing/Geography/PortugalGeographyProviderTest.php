<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Portugal\PortugalAddressFormatter;

it('formats Portuguese addresses with the hyphenated postcode left of the locality', function (): void {
    $formatted = app(PortugalAddressFormatter::class)->format(AddressData::from([
        'line1' => 'R. LEAL DA CÂMARA 31 RC ESQ',
        'line2' => 'ALGUEIRÃO',
        'city' => 'MEM MARTINS',
        'postcode' => '2725-079',
        'country_code' => 'PT',
    ]));

    expect($formatted)->toBe("R. LEAL DA CÂMARA 31 RC ESQ\nALGUEIRÃO\n2725-079 MEM MARTINS\nPortugal");
});
it('formats Portuguese Lisbon addresses with the parish postcode', function (): void {
    $formatted = app(PortugalAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Avenida da Liberdade 100',
        'city' => 'LISBOA',
        'postcode' => '1601-801',
        'country_code' => 'PT',
    ]));

    expect($formatted)->toBe("Avenida da Liberdade 100\n1601-801 LISBOA\nPortugal");
});
