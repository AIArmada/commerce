<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Senegal\SenegalAddressFormatter;
use AIArmada\Addressing\Geography\Senegal\SenegalGeographyProvider;

it('formats Senegalese addresses with the postcode left of the office', function (): void {
    $formatted = app(SenegalAddressFormatter::class)->format(AddressData::from([
        'line1' => '12 AVENUE CHEIKH ANTA DIOP',
        'city' => 'DAKAR',
        'postcode' => '12500',
        'country_code' => 'SN',
    ]));

    expect($formatted)->toBe("12 AVENUE CHEIKH ANTA DIOP\n12500 DAKAR\nSenegal");
});
it('prints matching Senegalese city and region once', function (): void {
    $formatted = app(SenegalAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 1534',
        'city' => 'Ziguinchor',
        'state' => 'Ziguinchor',
        'postcode' => '27000',
        'country_code' => 'SN',
    ]));

    expect($formatted)->toBe("BP 1534\n27000 Ziguinchor\nSenegal");
});
it('exposes corrected Senegalese region slugs and names', function (): void {
    $areas = app(SenegalGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('sn:region:diourbel')->name)->toBe('Diourbel')
        ->and($areas->get('sn:region:diourbel')->code)->toBe('DB')
        ->and($areas->get('sn:region:tambacounda')->name)->toBe('Tambacounda')
        ->and($areas->get('sn:region:tambacounda')->code)->toBe('TC')
        ->and($areas->get('sn:region:thies')->name)->toBe('Thiès')
        ->and($areas->get('sn:region:thies')->type)->toBe('region')
        ->and($areas->has('sn:region:diourbel-region'))->toBeFalse()
        ->and($areas->has('sn:region:tambacounda-region'))->toBeFalse()
        ->and($areas->has('sn:region:thies-region'))->toBeFalse();
});

it('ships 46 departments under regions with parent links', function (): void {
    $areas = app(SenegalGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'department');

    expect($l2)->toHaveCount(46)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('sn:department:dakar')->name)->toBe('Dakar')
        ->and($byId->get('sn:department:keur-massar')->name)->toBe('Keur Massar')
        ->and($byId->get('sn:department:dagana')->name)->toBe('Dagana');
});

it('labels tiers Région and Département', function (): void {
    $provider = app(SenegalGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['region' => 'Région', 'department' => 'Département'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
