<?php

declare(strict_types=1);

arch('strict types across package source')
    ->expect('AIArmada\FilamentCashierChip')
    ->toUseStrictTypes();

arch('no debugging helpers, env(), or imperative sleep in package source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'sleep', 'usleep', 'env'])
    ->each->not->toBeUsedIn('AIArmada\FilamentCashierChip');

arch('package source never imports host application code')
    ->expect('AIArmada\FilamentCashierChip')
    ->not->toUse('App');

arch('public Contracts and Data stay decoupled from Actions, Models, and Drivers')
    ->expect(['AIArmada\FilamentCashierChip\Contracts', 'AIArmada\FilamentCashierChip\Data'])
    ->not->toUse([
        'AIArmada\FilamentCashierChip\Actions',
        'AIArmada\FilamentCashierChip\Models',
        'AIArmada\FilamentCashierChip\Drivers',
        'AIArmada\FilamentCashierChip\Features',
        'Illuminate\Database\Eloquent',
        'Illuminate\Http',
    ]);
