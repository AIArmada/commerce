<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\IsleOfMan\IsleOfManGeographyProvider;

it('types the sheadings with the singular type key', function (): void {
    $areas = app(IsleOfManGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->where('type', 'sheading'))->toHaveCount(6)
        ->and($areas->where('type', 'sheadings'))->toBeEmpty()
        ->and($areas->get('im:sheading:ayre')->code)->toBe('01');
});
