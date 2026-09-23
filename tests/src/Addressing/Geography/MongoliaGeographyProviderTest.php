<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Mongolia\MongoliaAddressFormatter;
use AIArmada\Addressing\Geography\Mongolia\MongoliaGeographyProvider;

it('formats Mongolian addresses with the postcode right of the province', function (): void {
    $formatted = app(MongoliaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Jigjidjav street 9-11 toot',
        'line2' => '15th khoroo, Bayanzurkh Duureg',
        'state' => 'ULAANBAATAR',
        'postcode' => '14560',
        'country_code' => 'MN',
    ]));

    expect($formatted)->toBe("Jigjidjav street 9-11 toot\n15th khoroo, Bayanzurkh Duureg\nULAANBAATAR 14560\nMongolia");
});
it('formats Mongolian addresses with the district above the postcode province line', function (): void {
    $formatted = app(MongoliaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Jigjidjav street 9-11 toot',
        'city' => 'Bayanzurkh',
        'state' => 'Ulaanbaatar',
        'postcode' => '14560',
        'country_code' => 'MN',
    ]));

    expect($formatted)->toBe("Jigjidjav street 9-11 toot\nBayanzurkh\nUlaanbaatar 14560\nMongolia");
});

it('codes Ulaanbaatar as single digit 1 per ISO MN-1', function (): void {
    $areas = app(MongoliaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('mn:capital_city:ulaanbaatar')->code)->toBe('1');
});

it('ships 330 sums and 9 duuregs under provinces with parent links', function (): void {
    $areas = app(MongoliaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(339)
        ->and($areas->where('type', 'sum'))->toHaveCount(330)
        ->and($areas->where('type', 'duureg'))->toHaveCount(9)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('mn:sum:kharkhorin')->name)->toBe('Kharkhorin')
        ->and($byId->get('mn:sum:dalanzadgad')->name)->toBe('Dalanzadgad')
        ->and($byId->get('mn:duureg:ulaanbaatar:bayangol')->name)->toBe('Bayangol');
});

it('labels tiers Aimag, Sum and Düüreg', function (): void {
    $provider = app(MongoliaGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['province' => 'Aimag', 'sum' => 'Sum', 'duureg' => 'Düüreg'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
