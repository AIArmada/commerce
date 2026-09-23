<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Turkmenistan\TurkmenistanAddressFormatter;
use AIArmada\Addressing\Geography\Turkmenistan\TurkmenistanGeographyProvider;

it('formats Turkmen addresses with the postcode below the locality', function (): void {
    $formatted = app(TurkmenistanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'j. 19 otag 1',
        'line2' => 'kv. 122',
        'city' => 'BALKANABAT',
        'state' => 'Balkan',
        'postcode' => '745100',
        'country_code' => 'TM',
    ]));

    expect($formatted)->toBe("j. 19 otag 1\nkv. 122\nBALKANABAT\nBalkan\n745100\nTurkmenistan");
});
it('prints matching Turkmen city and capital once above the postcode', function (): void {
    $formatted = app(TurkmenistanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Galkynysh Street 1',
        'city' => 'ASHGABAT',
        'state' => 'Ashgabat',
        'postcode' => '744000',
        'country_code' => 'TM',
    ]));

    expect($formatted)->toBe("Galkynysh Street 1\nASHGABAT\n744000\nTurkmenistan");
});

it('ships 58 districts under regions with parent links', function (): void {
    $areas = app(TurkmenistanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($l2)->toHaveCount(58)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('tm:district:bagtyyarlyk')->name)->toBe('Bagtyýarlyk')
        ->and($byId->get('tm:district:tejen')->name)->toBe('Tejen')
        ->and($byId->get('tm:district:kerki')->name)->toBe('Kerki');
});

it('labels tiers Welaýat, Şäher and Etrap', function (): void {
    $provider = app(TurkmenistanGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['region' => 'Welaýat', 'city' => 'Şäher', 'district' => 'Etrap'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
