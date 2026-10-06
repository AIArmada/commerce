<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Niger\NigerGeographyProvider;

it('pins the verified Niger tree of 7 regions, Niamey, 66 departments, and 5 communes', function (): void {
    $areas = app(NigerGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'region'))->toHaveCount(7)
        ->and($areas->where('type', 'urban_community'))->toHaveCount(1)
        ->and($areas->where('type', 'department'))->toHaveCount(66)
        ->and($areas->where('type', 'commune'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'ne:region:tahoua'))->toHaveCount(13)
        ->and($areas->where('parentSourceId', 'ne:region:tillaberi'))->toHaveCount(13)
        ->and($areas->where('parentSourceId', 'ne:region:zinder'))->toHaveCount(11)
        ->and($areas->where('parentSourceId', 'ne:region:maradi'))->toHaveCount(9)
        ->and($areas->where('parentSourceId', 'ne:region:dosso'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'ne:region:agadez'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'ne:region:diffa'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'ne:urban_community:niamey'))->toHaveCount(5);

    // ISO 3166-2:NE codes; Departments-of-Niger oracle: the three
    // city departments parent to their regions, Niamey V commune
    // under the Niamey urban community.
    expect($byId->get('ne:region:tillaberi')->code)->toBe('6')
        ->and($byId->get('ne:region:zinder')->code)->toBe('7')
        ->and($byId->get('ne:urban_community:niamey')->code)->toBe('8')
        ->and($byId->get('ne:department:maradi-city')->parentSourceId)->toBe('ne:region:maradi')
        ->and($byId->get('ne:department:tahoua-city')->parentSourceId)->toBe('ne:region:tahoua')
        ->and($byId->get('ne:department:zinder-city')->parentSourceId)->toBe('ne:region:zinder')
        ->and($byId->get('ne:commune:niamey-v')->parentSourceId)->toBe('ne:urban_community:niamey');
});
