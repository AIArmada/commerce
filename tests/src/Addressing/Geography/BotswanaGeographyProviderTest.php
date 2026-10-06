<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Botswana\BotswanaGeographyProvider;

it('ships Orapa as the fifth town', function (): void {
    $areas = app(BotswanaGeographyProvider::class)->addressAreaSource()->areas();

    expect($areas->where('level', 1))->toHaveCount(17);

    $orapa = $areas->firstWhere('sourceId', 'bw:town:orapa');

    expect($orapa->name)->toBe('Orapa')
        ->and($orapa->type)->toBe('town')
        ->and($orapa->code)->toBe('OR');
});

it('pins the verified Botswana tree with the Selebi Phikwe spelling', function (): void {
    $areas = app(BotswanaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    // B8: Selibe -> Selebi (WP title + Statoids + gov.bw DailyNews
    // + citypopulation; ISO BW-SP stale). Source id stable.
    expect($areas->where('level', 1))->toHaveCount(17)
        ->and($areas->where('type', 'subdistrict'))->toHaveCount(23)
        ->and($byId->get('bw:town:selibe-phikwe')->name)->toBe('Selebi Phikwe')
        ->and($byId->get('bw:town:selibe-phikwe')->code)->toBe('SP')
        ->and($areas->where('parentSourceId', 'bw:district:central'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'bw:district:chobe'))->toHaveCount(0)
        ->and($areas->where('parentSourceId', 'bw:district:north-east'))->toHaveCount(0);
});
