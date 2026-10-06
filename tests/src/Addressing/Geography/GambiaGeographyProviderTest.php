<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Gambia\GambiaGeographyProvider;

it('pins the verified Gambia tree of 7 first-level areas and 42 districts', function (): void {
    $areas = app(GambiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'city'))->toHaveCount(2)
        ->and($areas->where('type', 'region'))->toHaveCount(5)
        ->and($areas->where('type', 'district'))->toHaveCount(42)
        ->and($areas->where('parentSourceId', 'gm:city:banjul'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'gm:region:central-river'))->toHaveCount(10)
        ->and($areas->where('parentSourceId', 'gm:region:west-coast'))->toHaveCount(9)
        ->and($areas->where('parentSourceId', 'gm:city:kanifing'))->toHaveCount(0);

    // Subdivisions-of-the-Gambia oracle (districts per LGA): Kanifing is
    // a single-district municipality kept terminal by design; ISO GM-W
    // keeps the "Western" name while the region ships as West Coast.
    expect($byId->get('gm:city:banjul')->code)->toBe('B')
        ->and($byId->get('gm:region:central-river')->code)->toBe('M')
        ->and($byId->get('gm:region:west-coast')->code)->toBe('W')
        ->and($byId->get('gm:region:west-coast')->name)->toBe('West Coast')
        ->and($byId->get('gm:district:kombo-north')->parentSourceId)->toBe('gm:region:west-coast')
        ->and($byId->get('gm:district:basse-fulladu-east')->parentSourceId)->toBe('gm:region:upper-river');
});
