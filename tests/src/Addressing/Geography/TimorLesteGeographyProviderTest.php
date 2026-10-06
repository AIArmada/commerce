<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\TimorLeste\TimorLesteGeographyProvider;

it('ships 14 ISO-coded L1 areas with 67 administrative posts', function (): void {
    $areas = app(TimorLesteGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'administrative_post');

    expect($areas->where('level', 1))->toHaveCount(14)
        ->and($l2)->toHaveCount(67)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('tl:municipality:atauro')->code)->toBe('AT')
        ->and($byId->get('tl:special_administrative_region:oecusse')->code)->toBe('OE')
        ->and($byId->get('tl:municipality:dili')->code)->toBe('DI')
        ->and($byId->get('tl:municipality:baucau')->code)->toBe('BA');
});

it('pins the verified TL tree of 4/4/8/6/7/5/5/5/4/6/4/5/4 posts', function (): void {
    $areas = app(TimorLesteGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('parentSourceId', 'tl:municipality:aileu'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'tl:municipality:ainaro'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'tl:municipality:baucau'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'tl:municipality:bobonaro'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'tl:municipality:cova-lima'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'tl:municipality:dili'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'tl:municipality:ermera'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'tl:municipality:lautem'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'tl:municipality:liquica'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'tl:municipality:manatuto'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'tl:municipality:manufahi'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'tl:municipality:viqueque'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'tl:special_administrative_region:oecusse'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'tl:municipality:atauro'))->toHaveCount(0);

    // The 3 posts created 2024-01-01 (Diploma Ministerial 40/2023).
    expect($byId->get('tl:administrative_post:loes')->parentSourceId)->toBe('tl:municipality:liquica')
        ->and($byId->get('tl:administrative_post:matebian')->parentSourceId)->toBe('tl:municipality:baucau')
        ->and($byId->get('tl:administrative_post:quelicai-antiga')->parentSourceId)->toBe('tl:municipality:baucau');
});
