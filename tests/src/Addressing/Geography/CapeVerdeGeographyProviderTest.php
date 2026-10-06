<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\CapeVerde\CapeVerdeGeographyProvider;

it('ships 22 ISO-coded concelhos plus 2 island groups at L1', function (): void {
    $areas = app(CapeVerdeGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'municipality'))->toHaveCount(22)
        ->and($areas->where('type', 'geographical_region'))->toHaveCount(2)
        ->and($byId->get('cv:geographical_region:barlavento-islands')->code)->toBe('B')
        ->and($byId->get('cv:geographical_region:sotavento-islands')->code)->toBe('S')
        ->and($byId->get('cv:municipality:praia')->code)->toBe('PR')
        ->and($byId->get('cv:municipality:sao-vicente')->code)->toBe('SV')
        ->and($byId->get('cv:municipality:sao-lourenco-dos-orgaos')->code)->toBe('SO')
        ->and($byId->get('cv:municipality:sao-miguel')->code)->toBe('SM')
        ->and($byId->get('cv:municipality:tarrafal-de-sao-nicolau')->code)->toBe('TS')
        ->and($byId->get('cv:municipality:ribeira-grande-de-santiago')->code)->toBe('RS');
});

it('pins the verified CV tree of 32 parishes under 22 concelhos', function (): void {
    $areas = app(CapeVerdeGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'parish');

    expect($l2)->toHaveCount(32)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($areas->where('parentSourceId', 'cv:municipality:ribeira-grande'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'cv:municipality:sao-domingos'))->toHaveCount(2)
        ->and($areas->where('parentSourceId', 'cv:municipality:ribeira-grande-de-santiago'))->toHaveCount(2)
        ->and($areas->where('parentSourceId', 'cv:municipality:brava'))->toHaveCount(2)
        ->and($areas->where('parentSourceId', 'cv:municipality:sao-filipe'))->toHaveCount(2)
        ->and($areas->where('parentSourceId', 'cv:municipality:boa-vista'))->toHaveCount(2)
        ->and($areas->where('parentSourceId', 'cv:municipality:ribeira-brava'))->toHaveCount(2)
        ->and($areas->where('parentSourceId', 'cv:municipality:porto-novo'))->toHaveCount(2)
        ->and($areas->where('parentSourceId', 'cv:municipality:praia'))->toHaveCount(1)
        ->and($areas->where('parentSourceId', 'cv:municipality:sal'))->toHaveCount(1)
        ->and($areas->where('parentSourceId', 'cv:municipality:paul'))->toHaveCount(1);

    // Same-name parishes in different concelhos carry prefixed ids.
    expect($byId->get('cv:parish:brava:sao-joao-baptista')->parentSourceId)->toBe('cv:municipality:brava')
        ->and($byId->get('cv:parish:maio:nossa-senhora-da-luz')->parentSourceId)->toBe('cv:municipality:maio')
        ->and($byId->get('cv:parish:ribeira-grande:nossa-senhora-do-rosario')->parentSourceId)->toBe('cv:municipality:ribeira-grande');
});
