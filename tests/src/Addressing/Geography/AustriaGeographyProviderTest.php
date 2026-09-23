<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Austria\AustriaAddressFormatter;
use AIArmada\Addressing\Geography\Austria\AustriaGeographyProvider;

it('formats Austrian addresses with the postcode left of the locality', function (): void {
    $formatted = app(AustriaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rennbahnweg 25/2/15',
        'city' => 'WIEN',
        'postcode' => '1220',
        'country_code' => 'AT',
    ]));

    expect($formatted)->toBe("Rennbahnweg 25/2/15\n1220 WIEN\nAustria");
});
it('formats Austrian rural addresses with the state below the postcode line', function (): void {
    $formatted = app(AustriaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Dorfstrasse 7',
        'city' => 'Hallstatt',
        'state' => 'Upper Austria',
        'postcode' => '4830',
        'country_code' => 'AT',
    ]));

    expect($formatted)->toBe("Dorfstrasse 7\n4830 Hallstatt\nUpper Austria\nAustria");
});

it('ships 93 districts/cities under states with parent links', function (): void {
    $areas = app(AustriaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->whereIn('type', ['district', 'statutory_city']);

    expect($l2)->toHaveCount(93)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('at:statutory_city:linz')->name)->toBe('Linz')
        ->and($byId->get('at:district:linz-land')->name)->toBe('Linz-Land')
        ->and($byId->get('at:statutory_city:salzburg')->name)->toBe('Salzburg');
});

it('labels tiers Bundesland, Bezirk and Statutarstadt', function (): void {
    $provider = app(AustriaGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['state' => 'Bundesland', 'district' => 'Bezirk', 'statutory_city' => 'Statutarstadt'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
