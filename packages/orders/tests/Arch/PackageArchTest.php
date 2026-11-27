<?php

declare(strict_types=1);

arch('strict types across package source')
    ->expect('AIArmada\Orders')
    ->toUseStrictTypes();

arch('no debugging helpers, env(), or imperative sleep in package source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'sleep', 'usleep', 'env'])
    ->each->not->toBeUsedIn('AIArmada\Orders');

arch('package source never imports host application code')
    ->expect('AIArmada\Orders')
    ->not->toUse('App');

arch('public Contracts and Data stay decoupled from Actions, Models, and Drivers')
    ->expect(['AIArmada\Orders\Contracts', 'AIArmada\Orders\Data'])
    ->not->toUse([
        'AIArmada\Orders\Actions',
        'AIArmada\Orders\Models',
        'AIArmada\Orders\Drivers',
        'AIArmada\Orders\Features',
        'Illuminate\Database\Eloquent',
        'Illuminate\Http',
    ]);
