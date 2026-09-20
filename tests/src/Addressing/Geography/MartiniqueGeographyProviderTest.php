<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Martinique\MartiniqueAddressFormatter;

it('formats Martinican addresses with the postcode left of the locality', function (): void {
    $formatted = app(MartiniqueAddressFormatter::class)->format(AddressData::from([
        'line1' => '25 RUE CARNOT',
        'city' => 'LA TRINITE',
        'postcode' => '97220',
        'country_code' => 'MQ',
    ]));

    expect($formatted)->toBe("25 RUE CARNOT\n97220 LA TRINITE\nMartinique");
});
it('formats Martinican Fort-de-France addresses with the town postcode', function (): void {
    $formatted = app(MartiniqueAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de la Liberté 9',
        'city' => 'FORT-DE-FRANCE',
        'postcode' => '97200',
        'country_code' => 'MQ',
    ]));

    expect($formatted)->toBe("Rue de la Liberté 9\n97200 FORT-DE-FRANCE\nMartinique");
});
