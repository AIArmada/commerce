<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Aruba\ArubaGeographyProvider;

it('pins the verified Aruba tree of 8 regions and the capital', function (): void {
    $areas = app(ArubaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'region'))->toHaveCount(8)
        ->and($areas->where('type', 'capital_city'))->toHaveCount(1);

    // No ISO 3166-2:AW codes; 01-09 synthetic. Eight CBS census
    // regions (English East/Nicolaas forms) plus the intentional
    // capital Oranjestad addressing row. No postcode system (UPU).
    expect($byId->get('aw:region:noord')->code)->toBe('01')
        ->and($byId->get('aw:region:oranjestad-west')->code)->toBe('02')
        ->and($byId->get('aw:region:oranjestad-east')->code)->toBe('03')
        ->and($byId->get('aw:region:paradera')->code)->toBe('04')
        ->and($byId->get('aw:region:san-nicolaas-noord')->code)->toBe('05')
        ->and($byId->get('aw:region:san-nicolaas-zuid')->code)->toBe('06')
        ->and($byId->get('aw:region:santa-cruz')->code)->toBe('07')
        ->and($byId->get('aw:region:savaneta')->code)->toBe('08')
        ->and($byId->get('aw:capital_city:oranjestad')->code)->toBe('09');
});
