<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Guyana\GuyanaGeographyProvider;

it('pins the verified Guyana tree of 10 regions, 65 NDCs and 10 towns', function (): void {
    $areas = app(GuyanaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'region'))->toHaveCount(10)
        ->and($areas->where('type', 'neighbourhood_democratic_council'))->toHaveCount(65)
        ->and($areas->where('type', 'town'))->toHaveCount(10)
        ->and($areas->where('parentSourceId', 'gy:region:east-berbice-corentyne'))->toHaveCount(19)
        ->and($areas->where('parentSourceId', 'gy:region:demerara-mahaica'))->toHaveCount(17)
        ->and($areas->where('parentSourceId', 'gy:region:essequibo-islands-west-demerara'))->toHaveCount(14)
        ->and($areas->where('parentSourceId', 'gy:region:mahaica-berbice'))->toHaveCount(10)
        ->and($areas->where('parentSourceId', 'gy:region:pomeroon-supenaam'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'gy:region:barima-waini'))->toHaveCount(3);

    // ISO 3166-2:GY codes; NDC oracle: Region 8 has no NDCs and
    // Region 9's NDC was dissolved in 2012, so Mahdia and Lethem
    // are lone towns; Corriverton/Rose Hall sit in Region 6.
    expect($byId->get('gy:region:upper-demerara-berbice')->code)->toBe('UD')
        ->and($byId->get('gy:region:upper-takutu-upper-essequibo')->code)->toBe('UT')
        ->and($byId->get('gy:region:east-berbice-corentyne')->code)->toBe('EB')
        ->and($byId->get('gy:town:georgetown')->parentSourceId)->toBe('gy:region:demerara-mahaica')
        ->and($byId->get('gy:town:rose-hall')->parentSourceId)->toBe('gy:region:east-berbice-corentyne')
        ->and($byId->get('gy:neighbourhood_democratic_council:bartica-ndc')->parentSourceId)->toBe('gy:region:cuyuni-mazaruni')
        ->and($byId->get('gy:neighbourhood_democratic_council:kwakwani-ndc')->parentSourceId)->toBe('gy:region:upper-demerara-berbice');
});
