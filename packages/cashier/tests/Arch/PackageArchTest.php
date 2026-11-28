<?php

declare(strict_types=1);

arch('strict types across package source')
    ->expect('AIArmada\Cashier')
    ->toUseStrictTypes();

arch('no debugging helpers, env(), or imperative sleep in package source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'sleep', 'usleep', 'env'])
    ->each->not->toBeUsedIn('AIArmada\Cashier');

arch('package source never imports host application code')
    ->expect('AIArmada\Cashier')
    ->not->toUse('App');

arch('public Contracts and Data stay decoupled from Actions, Models, and Drivers')
    ->expect(['AIArmada\Cashier\Contracts', 'AIArmada\Cashier\Data'])
    ->not->toUse([
        'AIArmada\Cashier\Actions',
        'AIArmada\Cashier\Models',
        'AIArmada\Cashier\Drivers',
        'AIArmada\Cashier\Features',
        'Illuminate\Database\Eloquent',
        'Illuminate\Http',
    ]);
