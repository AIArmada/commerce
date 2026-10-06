<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Fiji\FijiGeographyProvider;

it('ships 14 provinces under divisions with parent links', function (): void {
    $areas = app(FijiGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'province');

    expect($l2)->toHaveCount(14)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('fj:province:ba')->name)->toBe('Ba')
        ->and($byId->get('fj:province:cakaudrove')->parentSourceId)->toBe('fj:division:northern');
});

it('pins the verified Fiji ISO tree and the hyphenated Nadroga-Navosa', function (): void {
    $areas = app(FijiGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    // ISO 3166-2:FJ: divisions C/E/N/W, dependency R, provinces 01-14.
    // FJ-08 stays hyphenated (Provinces of Fiji 6x; ISO "and" form 0x).
    // No postcode system (UPU fji), so no postal files ship.
    expect($areas)->toHaveCount(19)
        ->and($byId->get('fj:division:central')->code)->toBe('C')
        ->and($byId->get('fj:dependency:rotuma')->code)->toBe('R')
        ->and($byId->get('fj:province:ba')->code)->toBe('01')
        ->and($byId->get('fj:province:nadroga-navosa')->name)->toBe('Nadroga-Navosa')
        ->and($byId->get('fj:province:nadroga-navosa')->code)->toBe('08')
        ->and($byId->get('fj:province:nadroga-navosa')->parentSourceId)->toBe('fj:division:western')
        ->and($byId->get('fj:province:tailevu')->code)->toBe('14')
        ->and($byId->get('fj:province:rewa')->parentSourceId)->toBe('fj:division:central');
});
