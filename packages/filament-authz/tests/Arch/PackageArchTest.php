<?php

declare(strict_types=1);

arch('strict types across package source')
    ->expect('AIArmada\FilamentAuthz')
    ->toUseStrictTypes();

arch('no debugging helpers, env(), or imperative sleep in package source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'sleep', 'usleep', 'env'])
    ->each->not->toBeUsedIn('AIArmada\FilamentAuthz');

arch('package source never imports host application code')
    ->expect('AIArmada\FilamentAuthz')
    ->not->toUse('App');

arch('public Contracts and Data stay decoupled from Actions, Models, and Drivers')
    ->expect(['AIArmada\FilamentAuthz\Contracts', 'AIArmada\FilamentAuthz\Data'])
    ->not->toUse([
        'AIArmada\FilamentAuthz\Actions',
        'AIArmada\FilamentAuthz\Models',
        'AIArmada\FilamentAuthz\Drivers',
        'AIArmada\FilamentAuthz\Features',
        'Illuminate\Database\Eloquent',
        'Illuminate\Http',
    ]);
