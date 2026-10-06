<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\IvoryCoast\IvoryCoastGeographyProvider;

it('pins the verified Ivory Coast tree of 14 districts and 31 regions', function (): void {
    $areas = app(IvoryCoastGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'district'))->toHaveCount(12)
        ->and($areas->where('type', 'autonomous_district'))->toHaveCount(2)
        ->and($areas->where('type', 'region'))->toHaveCount(31)
        ->and($areas->where('parentSourceId', 'ci:district:lacs'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'ci:district:bas-sassandra'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'ci:district:woroba'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'ci:autonomous_district:abidjan'))->toHaveCount(0)
        ->and($areas->where('parentSourceId', 'ci:autonomous_district:yamoussoukro'))->toHaveCount(0);

    // Districts-of-Ivory-Coast oracle: Bélier sits under Lacs (row moved
    // into the Lacs group in the M2 revisit); autonomous districts terminal.
    expect($byId->get('ci:region:belier')->parentSourceId)->toBe('ci:district:lacs')
        ->and($byId->get('ci:region:n-zi')->parentSourceId)->toBe('ci:district:lacs')
        ->and($byId->get('ci:region:gbeke')->parentSourceId)->toBe('ci:district:vallee-du-bandama')
        ->and($byId->get('ci:region:san-pedro')->parentSourceId)->toBe('ci:district:bas-sassandra')
        ->and($byId->get('ci:district:lacs')->code)->toBe('LC');
});
