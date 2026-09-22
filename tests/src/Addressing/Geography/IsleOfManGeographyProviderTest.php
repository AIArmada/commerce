<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\IsleOfMan\IsleOfManAddressFormatter;
use AIArmada\Addressing\Geography\IsleOfMan\IsleOfManGeographyProvider;

it('formats Manx addresses with the postcode below the post town', function (): void {
    $formatted = app(IsleOfManAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 177',
        'city' => 'DOUGLAS',
        'postcode' => 'IM99 1PS',
        'country_code' => 'IM',
    ]));

    expect($formatted)->toBe("P.O. Box 177\nDOUGLAS\nIM99 1PS\nIsle of Man");
});
it('formats Manx street addresses with the sheading below the town', function (): void {
    $formatted = app(IsleOfManAddressFormatter::class)->format(AddressData::from([
        'line1' => '50 Athol Street',
        'city' => 'Douglas',
        'state' => 'Middle',
        'postcode' => 'IM1 1JB',
        'country_code' => 'IM',
    ]));

    expect($formatted)->toBe("50 Athol Street\nDouglas\nMiddle\nIM1 1JB\nIsle of Man");
});

it('types the sheadings with the singular type key', function (): void {
    $areas = app(IsleOfManGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->where('type', 'sheading'))->toHaveCount(6)
        ->and($areas->where('type', 'sheadings'))->toBeEmpty()
        ->and($areas->get('im:sheading:ayre')->code)->toBe('01');
});

it('ships 21 local authorities under sheadings with parent links', function (): void {
    $areas = app(IsleOfManGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->whereIn('type', ['parish', 'town', 'district', 'village']);

    expect($l2)->toHaveCount(21)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('im:town:douglas')->name)->toBe('Douglas')
        ->and($byId->get('im:parish:braddan')->name)->toBe('Braddan')
        ->and($byId->get('im:village:port-erin')->name)->toBe('Port Erin');
});
