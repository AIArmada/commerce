<?php

declare(strict_types=1);

arch('strict types across FilamentPromotions package source')
    ->expect('AIArmada\FilamentPromotions')
    ->toUseStrictTypes();

arch('no debugging helpers, env(), or imperative sleep in FilamentPromotions package source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'sleep', 'usleep', 'time_nanosleep', 'env'])
    ->each->not->toBeUsedIn('AIArmada\FilamentPromotions');

arch('FilamentPromotions package source never imports host application code')
    ->expect('AIArmada\FilamentPromotions')
    ->not->toUse('App');
