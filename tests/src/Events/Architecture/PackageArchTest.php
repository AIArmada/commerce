<?php

declare(strict_types=1);

arch('strict types across package source')
    ->expect('AIArmada\Events')
    ->toUseStrictTypes();

arch('no debugging helpers, env(), or imperative sleep in package source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'sleep', 'usleep', 'time_nanosleep', 'env'])
    ->each->not->toBeUsedIn('AIArmada\Events');

arch('package source never imports host application code')
    ->expect('AIArmada\Events')
    ->not->toUse('App');

arch('public Contracts and Data stay decoupled from Eloquent, Http, Features, and Drivers')
    ->expect(['AIArmada\Events\Contracts', 'AIArmada\Events\Data'])
    ->not->toUse([
        'Illuminate\Database\Eloquent',
        'Illuminate\Http',
        'AIArmada\Events\Features',
        'AIArmada\Events\Drivers',
    ]);

arch('features never import concrete drivers directly')
    ->expect('AIArmada\Events\Features')
    ->not->toUse('AIArmada\Events\Drivers');
