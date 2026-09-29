<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Congo\CongoGeographyProvider;

it('ships the 15 departments including the October 2024 trio', function (): void {
    $areas = app(CongoGeographyProvider::class)->addressAreaSource()->areas();

    expect($areas->where('level', 1))->toHaveCount(15);

    $byId = $areas->keyBy('sourceId');

    expect($byId->get('cg:department:congo-oubangui')->name)->toBe('Congo-Oubangui')
        ->and($byId->get('cg:department:congo-oubangui')->code)->toBe('17')
        ->and($byId->get('cg:department:djoue-lefini')->name)->toBe('Djoué-Léfini')
        ->and($byId->get('cg:department:djoue-lefini')->code)->toBe('18')
        ->and($byId->get('cg:department:nkeni-alima')->name)->toBe('Nkéni-Alima')
        ->and($byId->get('cg:department:nkeni-alima')->code)->toBe('19');
});
