<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\FrenchSouthernTerritories\FrenchSouthernTerritoriesGeographyProvider;

it('pins the verified French Southern Territories tree of 5 districts', function (): void {
    $areas = app(FrenchSouthernTerritoriesGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'district'))->toHaveCount(5)
        ->and($areas->where('level', 1))->toHaveCount(5);

    // ISO 3166-2:TF includes no codes; 01-05 synthetic. Five
    // districts per the UPU atfEn enumeration; uninhabited.
    expect($byId->get('tf:district:adelie-land')->name)->toBe('Adélie Land')
        ->and($byId->get('tf:district:crozet-islands')->code)->toBe('02')
        ->and($byId->get('tf:district:kerguelen-islands')->code)->toBe('03')
        ->and($byId->get('tf:district:saint-paul-and-amsterdam-islands')->code)->toBe('04')
        ->and($byId->get('tf:district:scattered-islands')->code)->toBe('05');
});
