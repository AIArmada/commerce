<?php

declare(strict_types=1);

arch('strict types across Promotions package source')
    ->expect('AIArmada\Promotions')
    ->toUseStrictTypes();

arch('no debugging helpers, env(), or imperative sleep in Promotions package source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'sleep', 'usleep', 'time_nanosleep', 'env'])
    ->each->not->toBeUsedIn('AIArmada\Promotions');

arch('Promotions package source never imports host application code')
    ->expect('AIArmada\Promotions')
    ->not->toUse('App');
