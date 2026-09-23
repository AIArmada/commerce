<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Brunei\BruneiAddressFormatter;
use AIArmada\Addressing\Geography\Brunei\BruneiGeographyProvider;

it('formats Bruneian addresses with the town before the postcode', function (): void {
    $formatted = app(BruneiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'No. 7 Simpang 170, Jalan Muara',
        'city' => 'Muara',
        'postcode' => 'BT2328',
        'country_code' => 'BN',
        'components' => ['kampung' => 'Kampong Kapok'],
    ]));

    expect($formatted)->toBe("No. 7 Simpang 170, Jalan Muara\nKampong Kapok\nMuara BT2328\nBrunei Darussalam");
});

it('ships 39 mukims under districts with parent links', function (): void {
    $areas = app(BruneiGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'mukim');

    expect($l2)->toHaveCount(39)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('bn:mukim:pengkalan-batu')->name)->toBe('Pengkalan Batu')
        ->and($byId->get('bn:mukim:bangar')->name)->toBe('Bangar');
});

it('labels tiers Daerah and Mukim', function (): void {
    $provider = app(BruneiGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['district' => 'Daerah', 'mukim' => 'Mukim'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
