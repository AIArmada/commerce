<?php

declare(strict_types=1);

arch('strict types across package source')
    ->expect('AIArmada\Links')
    ->toUseStrictTypes();

arch('no debugging helpers, env(), or imperative sleep in package source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'sleep', 'usleep', 'env'])
    ->each->not->toBeUsedIn('AIArmada\Links');

arch('package source never imports host application code')
    ->expect('AIArmada\Links')
    ->not->toUse('App');

arch('public Contracts and Data stay decoupled from Actions, Models, and Drivers')
    ->expect(['AIArmada\Links\Contracts', 'AIArmada\Links\Data'])
    ->not->toUse([
        'AIArmada\Links\Actions',
        'AIArmada\Links\Models',
        'AIArmada\Links\Drivers',
        'AIArmada\Links\Features',
        'Illuminate\Database\Eloquent',
        'Illuminate\Http',
    ]);
