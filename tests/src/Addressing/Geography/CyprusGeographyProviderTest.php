<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Cyprus\CyprusAddressFormatter;
use AIArmada\Addressing\Geography\Cyprus\CyprusGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('formats Cypriot inbound addresses with the CY postcode prefix', function (): void {
    $formatted = app(CyprusAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Sofochleous 26',
        'city' => 'Strovolos',
        'postcode' => 'CY-2008',
        'country_code' => 'CY',
    ]));

    expect($formatted)->toBe("Sofochleous 26\nCY-2008 Strovolos\nCyprus");
});
it('formats Cypriot domestic addresses with a bare postcode', function (): void {
    $formatted = app(CyprusAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Griva Digeni 10',
        'city' => 'Larnaka',
        'postcode' => '6036',
        'country_code' => 'CY',
    ]));

    expect($formatted)->toBe("Griva Digeni 10\n6036 Larnaka\nCyprus");
});

it('ships 752 localities under their districts with postal locality roles', function (): void {
    $provider = app(CyprusGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'locality'))->toHaveCount(752)
        ->and($byId->get('cy:locality:paralimni')->parentSourceId)->toBe('cy:district:famagusta-magusa');

    $roles = $provider->areaRoles(new AddressCountry);

    expect($roles['cy:locality:paralimni'][0]['role'])->toBe('postal_locality');
});
