<?php

declare(strict_types=1);

arch('strict types across package source')
    ->expect('AIArmada\Contacting')
    ->toUseStrictTypes();

arch('no debugging helpers, env(), or imperative sleep in package source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'sleep', 'usleep', 'env'])
    ->each->not->toBeUsedIn('AIArmada\Contacting');

arch('package source never imports host application code')
    ->expect('AIArmada\Contacting')
    ->not->toUse('App');

arch('public Contracts and Data stay decoupled from Actions, Models, and Drivers')
    ->expect(['AIArmada\Contacting\Contracts', 'AIArmada\Contacting\Data'])
    ->not->toUse([
        'AIArmada\Contacting\Actions',
        'AIArmada\Contacting\Models',
        'AIArmada\Contacting\Drivers',
        'AIArmada\Contacting\Features',
        'Illuminate\Database\Eloquent',
        'Illuminate\Http',
    ]);
