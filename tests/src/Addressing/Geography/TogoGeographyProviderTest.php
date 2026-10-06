<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Togo\TogoGeographyProvider;

it('ships 39 prefectures under regions with parent links', function (): void {
    $areas = app(TogoGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(39)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'tg:region:savanes'))->toHaveCount(7)
        ->and($l2->where('parentSourceId', 'tg:region:kara'))->toHaveCount(7)
        ->and($l2->where('parentSourceId', 'tg:region:centrale'))->toHaveCount(5)
        ->and($l2->where('parentSourceId', 'tg:region:plateaux'))->toHaveCount(12)
        ->and($l2->where('parentSourceId', 'tg:region:maritime'))->toHaveCount(8)
        ->and($byId->get('tg:prefecture:kpendjal-ouest')->parentSourceId)->toBe('tg:region:savanes')
        ->and($byId->get('tg:prefecture:oti-sud')->parentSourceId)->toBe('tg:region:savanes')
        ->and($byId->get('tg:prefecture:mo')->parentSourceId)->toBe('tg:region:centrale')
        ->and($byId->get('tg:prefecture:kpele')->parentSourceId)->toBe('tg:region:plateaux')
        ->and($byId->get('tg:prefecture:agoe-nyive')->parentSourceId)->toBe('tg:region:maritime')
        ->and($byId->get('tg:prefecture:bas-mono')->parentSourceId)->toBe('tg:region:maritime');
});
