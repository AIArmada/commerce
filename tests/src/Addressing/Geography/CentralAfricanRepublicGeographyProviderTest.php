<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\CentralAfricanRepublic\CentralAfricanRepublicGeographyProvider;

it('ships 20 prefectures with Bangui and Sangha-Mbaéré retyped', function (): void {
    $areas = app(CentralAfricanRepublicGeographyProvider::class)->addressAreaSource()->areas();

    expect($areas->where('level', 1))->toHaveCount(20);

    $byId = $areas->keyBy('sourceId');

    expect($byId->has('cf:commune:bangui'))->toBeFalse()
        ->and($byId->get('cf:prefecture:bangui')->type)->toBe('prefecture')
        ->and($byId->has('cf:prefecture:sangha-mbaere'))->toBeFalse()
        ->and($byId->get('cf:economic_prefecture:sangha-mbaere')->type)->toBe('economic_prefecture')
        ->and($byId->get('cf:prefecture:lim-pende')->name)->toBe('Lim-Pendé')
        ->and($byId->get('cf:prefecture:mambere')->name)->toBe('Mambéré')
        ->and($byId->get('cf:prefecture:ouham-fafa')->name)->toBe('Ouham-Fafa');
});
