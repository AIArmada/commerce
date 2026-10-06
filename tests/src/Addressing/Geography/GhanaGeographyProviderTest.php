<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Ghana\GhanaGeographyProvider;

it('ships 16 regions and 261 assemblies with the verified category split', function (): void {
    $areas = app(GhanaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(277)
        ->and($areas->where('level', '1'))->toHaveCount(16)
        ->and($byId->get('gh:region:ahafo')->code)->toBe('AF')
        ->and($byId->get('gh:district:guan')->parentSourceId)->toBe('gh:region:oti');
});

it('holds the uncompleted November 2024 elevations out of the tree', function (): void {
    $areas = app(GhanaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    // Still districts/municipalities per 2025-2026 official docs, not elevated.
    foreach (['gh:district:south-tongu', 'gh:district:bole', 'gh:district:anloga', 'gh:municipality:techiman', 'gh:municipality:kpone-katamanso', 'gh:district:gomoa-east'] as $slug) {
        expect($areas->has($slug))->toBeTrue();
    }

    foreach (['gh:municipality:south-tongu', 'gh:municipality:bole', 'gh:metropolitan_city:techiman', 'gh:metropolitan_city:kpone-katamanso', 'gh:metropolitan_city:gomoa-east'] as $slug) {
        expect($areas->has($slug))->toBeFalse();
    }
});
