<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Iceland\IcelandAddressFormatter;
use AIArmada\Addressing\Geography\Iceland\IcelandGeographyProvider;

it('formats Icelandic addresses with the postcode left of the locality', function (): void {
    $formatted = app(IcelandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Tryggvagötu 5',
        'city' => 'HAFNARFIRÐI',
        'postcode' => '220',
        'country_code' => 'IS',
    ]));

    expect($formatted)->toBe("Tryggvagötu 5\n220 HAFNARFIRÐI\nIceland");
});
it('formats Icelandic capital addresses with the town postcode', function (): void {
    $formatted = app(IcelandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Ingólfsstræti 3',
        'city' => 'REYKJAVÍK',
        'postcode' => '121',
        'country_code' => 'IS',
    ]));

    expect($formatted)->toBe("Ingólfsstræti 3\n121 REYKJAVÍK\nIceland");
});
it('exposes corrected Icelandic municipality names', function (): void {
    $areas = app(IcelandGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('is:municipality:horgarsveit')->name)->toBe('Hörgársveit')
        ->and($areas->get('is:municipality:horgarsveit')->code)->toBe('HRG')
        ->and($areas->get('is:municipality:horgarsveit')->type)->toBe('municipality')
        ->and($areas->get('is:municipality:svalbardsstrandarhreppur')->name)->toBe('Svalbarðsstrandarhreppur')
        ->and($areas->get('is:municipality:svalbardsstrandarhreppur')->code)->toBe('SBT')
        ->and($areas->has('is:municipality:horarsveit'))->toBeFalse();
});

it('ships 61 municipalities under regions with parent links', function (): void {
    $areas = app(IcelandGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'municipality');

    expect($l2)->toHaveCount(61)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('is:municipality:reykjavik')->name)->toBe('Reykjavík')
        ->and($byId->get('is:municipality:reykjavik')->parentSourceId)->toBe('is:region:capital')
        ->and($byId->get('is:municipality:grindavik')->name)->toBe('Grindavíkurbær')
        ->and($byId->has('is:municipality:skagabygg'))->toBeFalse();
});

it('labels tiers Landsvæði and Sveitarfélag', function (): void {
    $provider = app(IcelandGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['region' => 'Landsvæði', 'municipality' => 'Sveitarfélag'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
