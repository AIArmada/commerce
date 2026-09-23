<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Belgium\BelgiumAddressFormatter;
use AIArmada\Addressing\Geography\Belgium\BelgiumGeographyProvider;

it('formats Belgian addresses with the postcode left of the locality', function (): void {
    $formatted = app(BelgiumAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Volklorenlaan 81 bus 15',
        'city' => 'Wilrijk',
        'postcode' => '2610',
        'country_code' => 'BE',
    ]));

    expect($formatted)->toBe("Volklorenlaan 81 bus 15\n2610 Wilrijk\nBelgium");
});
it('formats Belgian addresses without a province after the town', function (): void {
    $formatted = app(BelgiumAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de la Loi 16',
        'city' => 'Bruxelles',
        'postcode' => '1000',
        'country_code' => 'BE',
    ]));

    expect($formatted)->toBe("Rue de la Loi 16\n1000 Bruxelles\nBelgium");
});

it('labels Flemish and Walloon tiers in Dutch and French', function (): void {
    $provider = app(BelgiumGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe([])
        ->and($provider->stateAreaTypeLabels())->toBe([
            ['state_code' => 'VLG', 'type_labels' => ['region' => 'Gewest', 'province' => 'Provincie']],
            ['state_code' => 'WAL', 'type_labels' => ['region' => 'Région', 'province' => 'Province']],
        ]);
});
