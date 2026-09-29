<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Iceland\IcelandGeographyProvider;

it('exposes corrected Icelandic municipality names', function (): void {
    $areas = app(IcelandGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('is:municipality:horgarsveit')->name)->toBe('Hörgársveit')
        ->and($areas->get('is:municipality:horgarsveit')->code)->toBe('HRG')
        ->and($areas->get('is:municipality:horgarsveit')->type)->toBe('municipality')
        ->and($areas->get('is:municipality:svalbardsstrandarhreppur')->name)->toBe('Svalbarðsstrandarhreppur')
        ->and($areas->get('is:municipality:svalbardsstrandarhreppur')->code)->toBe('SBT')
        ->and($areas->has('is:municipality:horarsveit'))->toBeFalse();
});

it('ships 61 municipalities under regions with parent links', function (): void {
    $areas = app(IcelandGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'municipality');

    expect($l2)->toHaveCount(61)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('is:municipality:reykjavik')->name)->toBe('Reykjavík')
        ->and($byId->get('is:municipality:reykjavik')->parentSourceId)->toBe('is:region:capital')
        ->and($byId->get('is:municipality:grindavik')->name)->toBe('Grindavíkurbær')
        ->and($byId->has('is:municipality:skagabygg'))->toBeFalse();
});
