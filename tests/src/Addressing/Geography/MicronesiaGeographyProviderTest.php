<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Micronesia\MicronesiaGeographyProvider;

it('ships 75 municipalities and cities under states with parent links', function (): void {
    $areas = app(MicronesiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(75)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'fm:state:chuuk'))->toHaveCount(40)
        ->and($l2->where('parentSourceId', 'fm:state:kosrae'))->toHaveCount(4)
        ->and($l2->where('parentSourceId', 'fm:state:pohnpei'))->toHaveCount(11)
        ->and($l2->where('parentSourceId', 'fm:state:yap'))->toHaveCount(20)
        ->and($l2->where('type', 'city')->count())->toBe(2)
        ->and($byId->get('fm:city:weno')->parentSourceId)->toBe('fm:state:chuuk')
        ->and($byId->get('fm:municipality:utwe')->name)->toBe('Utwe');
});
