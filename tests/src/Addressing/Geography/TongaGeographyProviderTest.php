<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Tonga\TongaGeographyProvider;

it('ships 23 districts under divisions with parent links', function (): void {
    $areas = app(TongaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(23)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('to:district:kolofoou')->code)->toBe('TO-041')
        ->and($byId->get('to:district:haano')->code)->toBe('TO-025')
        ->and($byId->get('to:district:eua-motua')->parentSourceId)->toBe('to:division:eua')
        ->and($byId->get('to:district:niua-foou')->parentSourceId)->toBe('to:division:niuas');
});

it('pins the verified Tonga division names and per-division district counts', function (): void {
    $areas = app(TongaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    // ISO 3166-2:TO divisions (Statoids primary: Niuas, Ongo Niua a
    // variant); okina forms match WP division titles. District codes
    // TO-011..TO-056 per the WP district table (its duplicate TO-024
    // for Ha'ano is a typo; bundled TO-025 sequential is correct).
    expect($byId->get('to:division:eua')->name)->toBe('ʻEua')
        ->and($byId->get('to:division:haapai')->name)->toBe('Haʻapai')
        ->and($byId->get('to:division:niuas')->name)->toBe('Niuas')
        ->and($byId->get('to:division:tongatapu')->name)->toBe('Tongatapu')
        ->and($byId->get('to:division:vavau')->name)->toBe('Vavaʻu')
        ->and($areas->where('parentSourceId', 'to:division:eua'))->toHaveCount(2)
        ->and($areas->where('parentSourceId', 'to:division:haapai'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'to:division:niuas'))->toHaveCount(2)
        ->and($areas->where('parentSourceId', 'to:division:tongatapu'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'to:division:vavau'))->toHaveCount(6);
});
