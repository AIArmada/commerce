<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Suriname\SurinameAddressFormatter;
use AIArmada\Addressing\Geography\Suriname\SurinameGeographyProvider;

it('formats Surinamese addresses without a postcode system', function (): void {
    $formatted = app(SurinameAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Walapastraat 2',
        'line2' => 'Bloemendal',
        'city' => 'PARAMARIBO',
        'country_code' => 'SR',
    ]));

    expect($formatted)->toBe("Walapastraat 2\nBloemendal\nPARAMARIBO\nSuriname");
});
it('prints any supplied Surinamese code on its own line', function (): void {
    $formatted = app(SurinameAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Walapastraat 2',
        'city' => 'PARAMARIBO',
        'postcode' => '99999',
        'country_code' => 'SR',
    ]));

    expect($formatted)->toBe("Walapastraat 2\nPARAMARIBO\n99999\nSuriname");
});

it('ships 63 resorts under districts with parent links', function (): void {
    $areas = app(SurinameGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'resort');

    expect($l2)->toHaveCount(63)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('sr:resort:kwakoegron')->name)->toBe('Kwakoegron')
        ->and($byId->get('sr:resort:marshallkreek')->name)->toBe('Marshallkreek')
        ->and($byId->get('sr:resort:centrum')->name)->toBe('Centrum');
});
