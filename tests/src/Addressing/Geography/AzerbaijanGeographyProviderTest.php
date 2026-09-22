<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Azerbaijan\AzerbaijanAddressFormatter;
use AIArmada\Addressing\Geography\Azerbaijan\AzerbaijanGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('formats Azerbaijani addresses with the AZ postcode left of the locality', function (): void {
    $formatted = app(AzerbaijanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Zrifliyeva küç., ev 9',
        'city' => 'Bakı',
        'postcode' => 'AZ1010',
        'country_code' => 'AZ',
    ]));

    expect($formatted)->toBe("Zrifliyeva küç., ev 9\nAZ1010 Bakı\nAzerbaijan");
});
it('formats rural Azerbaijani addresses with the region below the postcode line', function (): void {
    $formatted = app(AzerbaijanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'H. Aliyev küç. 3',
        'city' => 'Nehrəm',
        'state' => 'Nakhchivan',
        'postcode' => 'AZ6715',
        'country_code' => 'AZ',
    ]));

    expect($formatted)->toBe("H. Aliyev küç. 3\nAZ6715 Nehrəm\nNakhchivan\nAzerbaijan");
});

it('assigns LA to Lankaran city and LAN to Lankaran district', function (): void {
    $areas = app(AzerbaijanGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('az:municipality:lankaran')->code)->toBe('LA')
        ->and($areas->get('az:district:lankaran')->code)->toBe('LAN')
        ->and($areas->get('az:district:khojavend')->code)->toBe('XVD');

    $names = app(AzerbaijanGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names['az:district:khojavend'][0]['name'])->toBe('Martuni');
});

it('exposes corrected Azerbaijani district slugs and names', function (): void {
    $areas = app(AzerbaijanGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('az:district:fuzuli')->name)->toBe('Fuzuli')
        ->and($areas->get('az:district:fuzuli')->code)->toBe('FUZ')
        ->and($areas->get('az:district:ismayilli')->name)->toBe('Ismayilli')
        ->and($areas->get('az:district:ismayilli')->code)->toBe('ISM')
        ->and($areas->get('az:district:khojaly')->name)->toBe('Khojaly')
        ->and($areas->get('az:district:khojaly')->code)->toBe('XCI')
        ->and($areas->get('az:district:gdby')->name)->toBe('Gadabay')
        ->and($areas->get('az:district:gdby')->type)->toBe('district')
        ->and($areas->get('az:district:agdam')->name)->toBe('Agdam')
        ->and($areas->has('az:district:fizuli'))->toBeFalse()
        ->and($areas->has('az:district:ismailli'))->toBeFalse()
        ->and($areas->has('az:district:khojali'))->toBeFalse();
});

it('ships 685 local municipalities under districts and cities', function (): void {
    $areas = app(AzerbaijanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(685)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('az:local_municipality:kis')->parentSourceId)->toBe('az:district:shaki')
        ->and($byId->get('az:local_municipality:asagi-quscu')->parentSourceId)->toBe('az:district:tovuz')
        ->and($byId->get('az:local_municipality:seki')->parentSourceId)->toBe('az:municipality:shaki');
});
