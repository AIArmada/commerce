<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\SaoTomeAndPrincipe\SaoTomeAndPrincipeGeographyProvider;

it('pins the verified São Tomé and Príncipe tree of 6 districts and Príncipe', function (): void {
    $areas = app(SaoTomeAndPrincipeGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'district'))->toHaveCount(6)
        ->and($areas->where('type', 'autonomous_region'))->toHaveCount(1);

    // ISO 3166-2:ST codes; B2 fixed Lemba to the official Lembá.
    // No postcode system (UPU stpEn carries no postcode section).
    expect($byId->get('st:district:agua-grande')->code)->toBe('01')
        ->and($byId->get('st:district:cantagalo')->code)->toBe('02')
        ->and($byId->get('st:district:caue')->code)->toBe('03')
        ->and($byId->get('st:district:lemba')->name)->toBe('Lembá')
        ->and($byId->get('st:district:lobata')->code)->toBe('05')
        ->and($byId->get('st:district:me-zochi')->code)->toBe('06')
        ->and($byId->get('st:autonomous_region:principe')->code)->toBe('P');
});
