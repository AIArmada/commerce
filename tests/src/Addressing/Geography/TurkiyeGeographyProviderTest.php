<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Turkiye\TurkiyeAddressFormatter;
use AIArmada\Addressing\Geography\Turkiye\TurkiyeGeographyProvider;

it('formats Turkish addresses with the postcode left of locality and province', function (): void {
    $formatted = app(TurkiyeAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Doğanbey Mah.',
        'city' => 'ULUS',
        'state' => 'ANKARA',
        'postcode' => '06101',
        'country_code' => 'TR',
    ]));

    expect($formatted)->toBe("Doğanbey Mah.\n06101 ULUS/ANKARA\nTürkiye");
});

it('ships 973 districts under provinces with parent links', function (): void {
    $areas = app(TurkiyeGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'district'))->toHaveCount(973)
        ->and($byId->get('tr:district:adana:ceyhan')->parentSourceId)->toBe('tr:province:adana');
});

it('labels tiers İl and İlçe', function (): void {
    $provider = app(TurkiyeGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['province' => 'İl', 'district' => 'İlçe'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
