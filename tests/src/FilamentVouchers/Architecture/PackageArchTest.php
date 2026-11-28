<?php

declare(strict_types=1);

arch('strict types across FilamentVouchers package source')
    ->expect('AIArmada\FilamentVouchers')
    ->toUseStrictTypes();

arch('no debugging helpers, env(), or imperative sleep in FilamentVouchers package source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'sleep', 'usleep', 'time_nanosleep', 'env'])
    ->each->not->toBeUsedIn('AIArmada\FilamentVouchers');

arch('FilamentVouchers package source never imports host application code')
    ->expect('AIArmada\FilamentVouchers')
    ->not->toUse('App');
