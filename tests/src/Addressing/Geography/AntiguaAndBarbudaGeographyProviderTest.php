<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\AntiguaAndBarbuda\AntiguaAndBarbudaGeographyProvider;

it('pins the verified Antigua and Barbuda tree of 6 parishes and 2 dependencies', function (): void {
    $areas = app(AntiguaAndBarbudaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'parish'))->toHaveCount(6)
        ->and($areas->where('type', 'dependency'))->toHaveCount(2);

    // ISO 3166-2:AG codes, exact set. No postcode system (UPU
    // atgEn is a contact block only).
    expect($byId->get('ag:parish:saint-george')->code)->toBe('03')
        ->and($byId->get('ag:parish:saint-john')->code)->toBe('04')
        ->and($byId->get('ag:parish:saint-mary')->code)->toBe('05')
        ->and($byId->get('ag:parish:saint-paul')->code)->toBe('06')
        ->and($byId->get('ag:parish:saint-peter')->code)->toBe('07')
        ->and($byId->get('ag:parish:saint-philip')->code)->toBe('08')
        ->and($byId->get('ag:dependency:barbuda')->code)->toBe('10')
        ->and($byId->get('ag:dependency:redonda')->code)->toBe('11');
});
