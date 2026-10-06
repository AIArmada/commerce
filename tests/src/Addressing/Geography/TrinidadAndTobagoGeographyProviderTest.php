<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\TrinidadAndTobago\TrinidadAndTobagoGeographyProvider;

it('types Diego Martin and Siparia as boroughs and San Fernando as a city', function (): void {
    $areas = app(TrinidadAndTobagoGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas)->toHaveCount(15)
        ->and($areas->get('tt:borough:diego-martin')->code)->toBe('DMN')
        ->and($areas->get('tt:borough:siparia')->code)->toBe('SIP')
        ->and($areas->get('tt:city:san-fernando')->code)->toBe('SFO')
        ->and($areas->has('tt:region:diego-martin'))->toBeFalse()
        ->and($areas->has('tt:region:san-fernando'))->toBeFalse()
        ->and($areas->has('tt:region:siparia'))->toBeFalse();
});

it('pins the verified Trinidad and Tobago ISO corporation codes', function (): void {
    $areas = app(TrinidadAndTobagoGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    // ISO 3166-2:TT codes, exact set (B5 re-verified). TT runs a
    // 6-digit postcode system but no public district directory
    // exists, so no postal files ship (documented gap).
    expect($byId->get('tt:borough:arima')->code)->toBe('ARI')
        ->and($byId->get('tt:borough:chaguanas')->code)->toBe('CHA')
        ->and($byId->get('tt:region:couva-tabaquite-talparo')->code)->toBe('CTT')
        ->and($byId->get('tt:region:mayaro-rio-claro')->code)->toBe('MRC')
        ->and($byId->get('tt:region:penal-debe')->code)->toBe('PED')
        ->and($byId->get('tt:borough:point-fortin')->code)->toBe('PTF')
        ->and($byId->get('tt:city:port-of-spain')->code)->toBe('POS')
        ->and($byId->get('tt:region:princes-town')->code)->toBe('PRT')
        ->and($byId->get('tt:region:san-juan-laventille')->code)->toBe('SJL')
        ->and($byId->get('tt:region:sangre-grande')->code)->toBe('SGE')
        ->and($byId->get('tt:ward:tobago')->code)->toBe('TOB')
        ->and($byId->get('tt:region:tunapuna-piarco')->code)->toBe('TUP');
});
