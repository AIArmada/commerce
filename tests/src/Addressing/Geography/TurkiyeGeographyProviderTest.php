<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Turkiye\TurkiyeGeographyProvider;

it('ships 973 districts under provinces with parent links', function (): void {
    $areas = app(TurkiyeGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'district'))->toHaveCount(973)
        ->and($byId->get('tr:district:adana:ceyhan')->parentSourceId)->toBe('tr:province:adana');
});
