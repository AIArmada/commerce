<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Japan\JapanGeographyProvider;

it('types municipalities by kind from the kanji suffix', function (): void {
    $areas = app(JapanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'city'))->toHaveCount(792)
        ->and($areas->where('type', 'town'))->toHaveCount(743)
        ->and($areas->where('type', 'village'))->toHaveCount(189)
        ->and($areas->where('type', 'ward'))->toHaveCount(23)
        ->and($byId->get('jp:municipality:13101')->type)->toBe('ward')
        ->and($byId->get('jp:municipality:10523')->type)->toBe('town')
        ->and($byId->get('jp:municipality:01695')->type)->toBe('village');
});
