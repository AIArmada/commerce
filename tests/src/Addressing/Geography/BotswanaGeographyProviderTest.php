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
