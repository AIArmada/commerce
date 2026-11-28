<?php

declare(strict_types=1);

arch('strict types across package source')
    ->expect('AIArmada\Tax')
    ->toUseStrictTypes();

arch('no debugging helpers, env(), or imperative sleep in package source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'sleep', 'usleep', 'env'])
    ->each->not->toBeUsedIn('AIArmada\Tax');

arch('package source never imports host application code')
    ->expect('AIArmada\Tax')
    ->not->toUse('App');

arch('public Contracts and Data stay decoupled from Actions, Models, and Drivers')
    ->expect(['AIArmada\Tax\Contracts', 'AIArmada\Tax\Data'])
    ->not->toUse([
        'AIArmada\Tax\Actions',
        'AIArmada\Tax\Models',
        'AIArmada\Tax\Drivers',
        'AIArmada\Tax\Features',
        'Illuminate\Database\Eloquent',
        'Illuminate\Http',
    ]);
