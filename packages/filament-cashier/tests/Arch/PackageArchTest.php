<?php

declare(strict_types=1);

arch('strict types across package source')
    ->expect('AIArmada\FilamentCashier')
    ->toUseStrictTypes();

arch('no debugging helpers, env(), or imperative sleep in package source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'sleep', 'usleep', 'env'])
    ->each->not->toBeUsedIn('AIArmada\FilamentCashier');

arch('package source never imports host application code')
    ->expect('AIArmada\FilamentCashier')
    ->not->toUse('App');

arch('public Contracts and Data stay decoupled from Actions, Models, and Drivers')
    ->expect(['AIArmada\FilamentCashier\Contracts', 'AIArmada\FilamentCashier\Data'])
    ->not->toUse([
        'AIArmada\FilamentCashier\Actions',
        'AIArmada\FilamentCashier\Models',
        'AIArmada\FilamentCashier\Drivers',
        'AIArmada\FilamentCashier\Features',
        'Illuminate\Database\Eloquent',
        'Illuminate\Http',
    ]);
