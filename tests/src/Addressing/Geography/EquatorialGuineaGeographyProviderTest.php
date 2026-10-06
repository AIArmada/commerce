<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\EquatorialGuinea\EquatorialGuineaGeographyProvider;

it('ships 8 provinces under regions with parent links', function (): void {
    $areas = app(EquatorialGuineaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'province');

    expect($l2)->toHaveCount(8)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('gq:province:annobon')->name)->toBe('Annobón')
        ->and($byId->get('gq:province:djibloho')->parentSourceId)->toBe('gq:region:rio-muni');
});

it('pins the verified Equatorial Guinea ISO region and province codes', function (): void {
    $areas = app(EquatorialGuineaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    // ISO 3166-2:GQ codes incl. DJ Djibloho. No postcode system.
    expect($byId->get('gq:region:rio-muni')->code)->toBe('C')
        ->and($byId->get('gq:region:insular')->code)->toBe('I')
        ->and($byId->get('gq:province:bioko-norte')->code)->toBe('BN')
        ->and($byId->get('gq:province:bioko-sur')->code)->toBe('BS')
        ->and($byId->get('gq:province:centro-sur')->code)->toBe('CS')
        ->and($byId->get('gq:province:djibloho')->code)->toBe('DJ')
        ->and($byId->get('gq:province:kie-ntem')->code)->toBe('KN')
        ->and($byId->get('gq:province:litoral')->code)->toBe('LI')
        ->and($byId->get('gq:province:wele-nzas')->code)->toBe('WN')
        ->and($areas->where('parentSourceId', 'gq:region:insular'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'gq:region:rio-muni'))->toHaveCount(5);
});
