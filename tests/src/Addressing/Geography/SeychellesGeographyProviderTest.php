<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Seychelles\SeychellesGeographyProvider;

it('pins the verified Seychelles tree of 27 districts', function (): void {
    $areas = app(SeychellesGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'district'))->toHaveCount(27)
        ->and($areas->where('level', 1))->toHaveCount(27);

    // ISO 3166-2:SC codes, exact set incl. SC-26/27 Ile Perseverance
    // I/II (OBP 2020-11-24). Local French name forms, not the ISO
    // ASCII renderings. No postcode system per the UPU syc profile
    // + absent GeoNames SC.zip, so no postal files ship.
    expect($byId->get('sc:district:anse-aux-pins')->code)->toBe('01')
        ->and($byId->get('sc:district:anse-boileau')->code)->toBe('02')
        ->and($byId->get('sc:district:anse-etoile')->code)->toBe('03')
        ->and($byId->get('sc:district:au-cap')->code)->toBe('04')
        ->and($byId->get('sc:district:anse-royale')->code)->toBe('05')
        ->and($byId->get('sc:district:baie-lazare')->code)->toBe('06')
        ->and($byId->get('sc:district:baie-sainte-anne')->code)->toBe('07')
        ->and($byId->get('sc:district:beau-vallon')->code)->toBe('08')
        ->and($byId->get('sc:district:bel-air')->code)->toBe('09')
        ->and($byId->get('sc:district:bel-ombre')->code)->toBe('10')
        ->and($byId->get('sc:district:cascade')->code)->toBe('11')
        ->and($byId->get('sc:district:glacis')->code)->toBe('12')
        ->and($byId->get('sc:district:grandanse-mahe')->code)->toBe('13')
        ->and($byId->get('sc:district:grandanse-praslin')->code)->toBe('14')
        ->and($byId->get('sc:district:la-digue')->code)->toBe('15')
        ->and($byId->get('sc:district:la-riviere-anglaise')->code)->toBe('16')
        ->and($byId->get('sc:district:mont-buxton')->code)->toBe('17')
        ->and($byId->get('sc:district:mont-fleuri')->code)->toBe('18')
        ->and($byId->get('sc:district:plaisance')->code)->toBe('19')
        ->and($byId->get('sc:district:pointe-la-rue')->code)->toBe('20')
        ->and($byId->get('sc:district:port-glaud')->code)->toBe('21')
        ->and($byId->get('sc:district:saint-louis')->code)->toBe('22')
        ->and($byId->get('sc:district:takamaka')->code)->toBe('23')
        ->and($byId->get('sc:district:les-mamelles')->code)->toBe('24')
        ->and($byId->get('sc:district:roche-caiman')->code)->toBe('25')
        ->and($byId->get('sc:district:ile-perseverance-i')->code)->toBe('26')
        ->and($byId->get('sc:district:ile-perseverance-ii')->code)->toBe('27');
});

it('keeps the local French district name forms', function (): void {
    $byId = app(SeychellesGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($byId->get('sc:district:anse-aux-pins')->name)->toBe('Anse-aux-Pins')
        ->and($byId->get('sc:district:grandanse-mahe')->name)->toBe('Grand\'Anse Mahé')
        ->and($byId->get('sc:district:grandanse-praslin')->name)->toBe('Grand\'Anse Praslin')
        ->and($byId->get('sc:district:la-riviere-anglaise')->name)->toBe('La Rivière Anglaise')
        ->and($byId->get('sc:district:pointe-la-rue')->name)->toBe('Pointe La Rue');
});
