<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Tajikistan\TajikistanAddressFormatter;
use AIArmada\Addressing\Geography\Tajikistan\TajikistanGeographyProvider;

it('formats Tajik addresses with the postcode left of the locality', function (): void {
    $formatted = app(TajikistanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Khoutchandi 7',
        'city' => 'GARM',
        'postcode' => '735450',
        'country_code' => 'TJ',
    ]));

    expect($formatted)->toBe("Khoutchandi 7\n735450 GARM\nTajikistan");
});
it('prints matching Tajik city and capital region once', function (): void {
    $formatted = app(TajikistanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rudaki Avenue 1',
        'city' => 'DUSHANBE',
        'state' => 'Dushanbe',
        'postcode' => '734012',
        'country_code' => 'TJ',
    ]));

    expect($formatted)->toBe("Rudaki Avenue 1\n734012 DUSHANBE\nTajikistan");
});

it('ships 69 districts/cities under regions with parent links', function (): void {
    $areas = app(TajikistanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->whereIn('type', ['district', 'city']);

    expect($l2)->toHaveCount(69)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('tj:district:bobojon-ghafurov')->name)->toBe('Bobojon Ghafurov')
        ->and($byId->get('tj:city:khujand')->name)->toBe('Khujand')
        ->and($byId->get('tj:district:ibn-sina')->name)->toBe('Ibn Sina');
});
