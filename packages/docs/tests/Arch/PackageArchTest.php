<?php

declare(strict_types=1);

arch('strict types across package source')
    ->expect('AIArmada\Docs')
    ->toUseStrictTypes();

arch('no debugging helpers, env(), or imperative sleep in package source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'sleep', 'usleep', 'env'])
    ->each->not->toBeUsedIn('AIArmada\Docs');

arch('package source never imports host application code')
    ->expect('AIArmada\Docs')
    ->not->toUse('App');

arch('public Contracts and Data stay decoupled from Actions, Models, and Drivers')
    ->expect(['AIArmada\Docs\Contracts', 'AIArmada\Docs\Data'])
    ->not->toUse([
        'AIArmada\Docs\Actions',
        'AIArmada\Docs\Models',
        'AIArmada\Docs\Drivers',
        'AIArmada\Docs\Features',
        'Illuminate\Database\Eloquent',
        'Illuminate\Http',
    ]);
