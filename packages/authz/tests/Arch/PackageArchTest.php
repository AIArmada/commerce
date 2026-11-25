<?php

declare(strict_types=1);

arch('strict types across package source')
    ->expect('AIArmada\Authz')
    ->toUseStrictTypes();

arch('no debugging helpers, env(), or imperative sleep in package source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'sleep', 'usleep', 'env'])
    ->each->not->toBeUsedIn('AIArmada\Authz');

arch('package source never imports host application code')
    ->expect('AIArmada\Authz')
    ->not->toUse('App');

arch('public Contracts and Data stay decoupled from Actions, Models, and Drivers')
    ->expect(['AIArmada\Authz\Contracts', 'AIArmada\Authz\Data'])
    ->not->toUse([
        'AIArmada\Authz\Actions',
        'AIArmada\Authz\Models',
        'AIArmada\Authz\Drivers',
        'AIArmada\Authz\Features',
        'Illuminate\Database\Eloquent',
        'Illuminate\Http',
    ]);
