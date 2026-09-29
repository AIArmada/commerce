<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\MarshallIslands\MarshallIslandsGeographyProvider;

it('nests 24 municipalities under the 2 chains', function (): void {
    $areas = app(MarshallIslandsGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('level', 1))->toHaveCount(2)
        ->and($areas->where('level', 2))->toHaveCount(24)
        ->and($areas->where('level', 2)->where('parentSourceId', 'mh:chain:ralik'))->toHaveCount(14)
        ->and($areas->where('level', 2)->where('parentSourceId', 'mh:chain:ratak'))->toHaveCount(10)
        ->and($byId->get('mh:municipality:majuro')->parentSourceId)->toBe('mh:chain:ratak')
        ->and($byId->get('mh:municipality:kwajalein')->parentSourceId)->toBe('mh:chain:ralik');
});
