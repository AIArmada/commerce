<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\SolomonIslands\SolomonIslandsGeographyProvider;

it('ships 183 wards under provinces with parent links', function (): void {
    $areas = app(SolomonIslandsGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'ward');

    expect($l2)->toHaveCount(183)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('sb:ward:nggosi')->code)->toBe('SB1010431001')
        ->and($byId->get('sb:ward:rove-lengakiki')->name)->toBe('Rove - Lengakiki')
        ->and($byId->get('sb:ward:vonunu')->name)->toBe('Vonunu');
});

it('ships 10 ISO-coded first-level areas with B16 per-province ward counts', function (): void {
    $areas = app(SolomonIslandsGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);

    expect($areas)->toHaveCount(193)
        ->and($l1)->toHaveCount(10)
        ->and($l1->where('type', 'province'))->toHaveCount(9)
        ->and($l1->where('type', 'capital_territory'))->toHaveCount(1)
        ->and($l1->map->code->sort()->values()->all())->toBe(['CE', 'CH', 'CT', 'GU', 'IS', 'MK', 'ML', 'RB', 'TE', 'WE'])
        ->and($byId->get('sb:province:makira-ulawa')->name)->toBe('Makira-Ulawa')
        ->and($byId->get('sb:province:rennell-and-bellona')->name)->toBe('Rennell and Bellona')
        // Per-province ward counts (COD-AB SINSO geography; citypopulation agrees).
        ->and($areas->where('parentSourceId', 'sb:province:central'))->toHaveCount(13)
        ->and($areas->where('parentSourceId', 'sb:province:choiseul'))->toHaveCount(14)
        ->and($areas->where('parentSourceId', 'sb:province:guadalcanal'))->toHaveCount(22)
        ->and($areas->where('parentSourceId', 'sb:capital_territory:honiara'))->toHaveCount(12)
        ->and($areas->where('parentSourceId', 'sb:province:malaita'))->toHaveCount(33)
        ->and($areas->where('parentSourceId', 'sb:province:western'))->toHaveCount(26)
        // Held SINSO spellings (verbatim official; directories differ).
        ->and($byId->get('sb:ward:mbanika')->name)->toBe('Mbanika')
        ->and($byId->get('sb:ward:waghina')->name)->toBe('Waghina')
        ->and($byId->get('sb:ward:havulei')->name)->toBe('Havulei')
        ->and($byId->get('sb:ward:west-baegu-fatale')->name)->toBe("West Baegu \u{a0}- Fatale");
});
